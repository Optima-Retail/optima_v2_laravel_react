<?php

declare(strict_types=1);

namespace App\Domain\Config\Articles\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Article;
use App\Models\ArticleClient;
use App\Models\ArticleLanguage;
use App\Models\CompanyRelationship;
use App\Models\Language;
use App\Support\ListQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ArticleService
{
    private const DEFAULT_LANGUAGE_ID = 1;

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Article>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'code', 'is_deletable', 'created_at'],
            'code',
        );

        return Article::query()
            ->with(['languages'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('code', 'like', "%{$search}%")
                        ->orWhereHas('languages', function ($languages) use ($search): void {
                            $languages
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (Article $article): array => $this->toListItem($article));
    }

    /**
     * @param  array{
     *     code: string,
     *     is_deletable?: bool,
     *     translations?: list<array{language_id: int, name: string, description?: string|null}>,
     *     clients?: list<array{company_relationship_id: int, sale_price: float|int|string}>
     * }  $data
     */
    public function create(array $data): Article
    {
        return DB::transaction(function () use ($data): Article {
            $article = Article::query()->create([
                'code' => $data['code'],
                'is_deletable' => (bool) ($data['is_deletable'] ?? true),
            ]);

            $this->syncTranslations($article, $data['translations'] ?? []);
            $this->syncClients($article, $data['clients'] ?? []);

            return $article->load(['languages', 'clients']);
        });
    }

    /**
     * @param  array{
     *     code: string,
     *     is_deletable?: bool,
     *     translations?: list<array{language_id: int, name: string, description?: string|null}>,
     *     clients?: list<array{company_relationship_id: int, sale_price: float|int|string}>
     * }  $data
     */
    public function update(Article $article, array $data): Article
    {
        return DB::transaction(function () use ($article, $data): Article {
            $article->update([
                'code' => $data['code'],
                'is_deletable' => (bool) ($data['is_deletable'] ?? true),
            ]);

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations($article, $data['translations'] ?? []);
            }

            if (array_key_exists('clients', $data)) {
                $this->syncClients($article, $data['clients'] ?? []);
            }

            return $article->fresh(['languages', 'clients']) ?? $article;
        });
    }

    public function delete(Article $article): void
    {
        if (! $article->is_deletable) {
            throw new AuthorizationException('This article cannot be deleted.');
        }

        if ($article->trashed()) {
            return;
        }

        DB::transaction(function () use ($article): void {
            $article->delete();
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function languageOptions(): array
    {
        return Language::query()
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'label' => "{$language->name} ({$language->code})",
            ])
            ->all();
    }

    /**
     * Articles linked to a customer relationship with sale prices.
     *
     * @return list<array{id: int, article_id: int, article_code: string, article_name: string|null, sale_price: string}>
     */
    public function forClient(CompanyRelationship $relationship): array
    {
        return ArticleClient::query()
            ->with(['article.languages'])
            ->where('company_relationship_id', $relationship->id)
            ->orderBy('article_id')
            ->get()
            ->map(function (ArticleClient $row): array {
                $article = $row->article;
                $translation = $article?->languages->firstWhere('language_id', self::DEFAULT_LANGUAGE_ID)
                    ?? $article?->languages->sortBy('language_id')->first();

                return [
                    'id' => $row->id,
                    'article_id' => $row->article_id,
                    'article_code' => $article?->code ?? '',
                    'article_name' => $translation?->name,
                    'sale_price' => (string) $row->sale_price,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Replace the client's article rates with the given rows.
     *
     * @param  list<array{id?: int|null, article_id: int, sale_price: float|int|string}>  $rows
     * @return list<array{id: int, article_id: int, article_code: string, article_name: string|null, sale_price: string}>
     */
    public function syncForClient(CompanyRelationship $relationship, array $rows): array
    {
        return DB::transaction(function () use ($relationship, $rows): array {
            $normalized = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $articleId = (int) ($row['article_id'] ?? 0);
                if ($articleId <= 0 || ! array_key_exists('sale_price', $row) || $row['sale_price'] === '' || $row['sale_price'] === null) {
                    continue;
                }

                $normalized[$articleId] = [
                    'sale_price' => $row['sale_price'],
                ];
            }

            $keepArticleIds = array_keys($normalized);

            ArticleClient::query()
                ->where('company_relationship_id', $relationship->id)
                ->when(
                    $keepArticleIds === [],
                    fn ($query) => $query->delete(),
                    fn ($query) => $query->whereNotIn('article_id', $keepArticleIds)->delete(),
                );

            foreach ($normalized as $articleId => $content) {
                ArticleClient::query()->updateOrCreate(
                    [
                        'article_id' => $articleId,
                        'company_relationship_id' => $relationship->id,
                    ],
                    ['sale_price' => $content['sale_price']],
                );
            }

            return $this->forClient($relationship);
        });
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function articleOptions(): array
    {
        return Article::query()
            ->with('languages')
            ->orderBy('code')
            ->get()
            ->map(function (Article $article): array {
                $translation = $article->languages->firstWhere('language_id', self::DEFAULT_LANGUAGE_ID)
                    ?? $article->languages->sortBy('language_id')->first();
                $name = $translation?->name;
                $label = $name ? "{$article->code} — {$name}" : $article->code;

                return [
                    'id' => $article->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function clientOptions(): array
    {
        /** @var Collection<int, CompanyRelationship> $relationships */
        $relationships = CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename')
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->orderBy('id')
            ->get();

        return $relationships
            ->map(function (CompanyRelationship $relationship): array {
                $company = $relationship->relatedCompany;
                $label = $company?->tradename
                    ? "{$company->name} ({$company->tradename})"
                    : ($company?->name ?? "#{$relationship->id}");

                return [
                    'id' => $relationship->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     is_deletable: bool,
     *     translations: list<array{language_id: int, name: string, description: string}>,
     *     clients: list<array{company_relationship_id: int, sale_price: string}>
     * }
     */
    public function toFormData(Article $article): array
    {
        $article->loadMissing(['languages', 'clients']);

        $translations = $article->languages
            ->sortBy('language_id')
            ->values()
            ->map(fn (ArticleLanguage $row): array => [
                'language_id' => $row->language_id,
                'name' => $row->name,
                'description' => $row->description ?? '',
            ])
            ->all();

        $clients = $article->clients
            ->sortBy('company_relationship_id')
            ->values()
            ->map(fn (ArticleClient $row): array => [
                'company_relationship_id' => $row->company_relationship_id,
                'sale_price' => (string) $row->sale_price,
            ])
            ->all();

        return [
            'id' => $article->id,
            'code' => $article->code,
            'is_deletable' => $article->is_deletable,
            'translations' => $translations,
            'clients' => $clients,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string|null,
     *     is_deletable: bool,
     *     created_at: string|null
     * }
     */
    public function toListItem(Article $article): array
    {
        $article->loadMissing('languages');

        $translation = $article->languages->firstWhere('language_id', self::DEFAULT_LANGUAGE_ID)
            ?? $article->languages->sortBy('language_id')->first();

        return [
            'id' => $article->id,
            'code' => $article->code,
            'name' => $translation?->name,
            'is_deletable' => $article->is_deletable,
            'created_at' => $article->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array{language_id?: int|string, name?: string, description?: string|null}>  $translations
     */
    private function syncTranslations(Article $article, array $translations): void
    {
        $normalized = [];

        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }

            $languageId = (int) ($row['language_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));

            if ($languageId <= 0 || $name === '') {
                continue;
            }

            $normalized[$languageId] = [
                'name' => $name,
                'description' => ($row['description'] ?? null) !== null && trim((string) $row['description']) !== ''
                    ? trim((string) $row['description'])
                    : null,
            ];
        }

        $keepIds = array_keys($normalized);

        $article->languages()
            ->when(
                $keepIds === [],
                fn ($query) => $query->delete(),
                fn ($query) => $query->whereNotIn('language_id', $keepIds)->delete(),
            );

        foreach ($normalized as $languageId => $content) {
            $article->languages()->updateOrCreate(
                ['language_id' => $languageId],
                [
                    'name' => $content['name'],
                    'description' => $content['description'],
                ],
            );
        }
    }

    /**
     * @param  list<array{company_relationship_id?: int|string, sale_price?: float|int|string}>  $clients
     */
    private function syncClients(Article $article, array $clients): void
    {
        $normalized = [];

        foreach ($clients as $row) {
            if (! is_array($row)) {
                continue;
            }

            $relationshipId = (int) ($row['company_relationship_id'] ?? 0);

            if ($relationshipId <= 0 || ! array_key_exists('sale_price', $row) || $row['sale_price'] === '' || $row['sale_price'] === null) {
                continue;
            }

            $normalized[$relationshipId] = [
                'sale_price' => $row['sale_price'],
            ];
        }

        $keepIds = array_keys($normalized);

        $article->clients()
            ->when(
                $keepIds === [],
                fn ($query) => $query->delete(),
                fn ($query) => $query->whereNotIn('company_relationship_id', $keepIds)->delete(),
            );

        foreach ($normalized as $relationshipId => $content) {
            $article->clients()->updateOrCreate(
                ['company_relationship_id' => $relationshipId],
                ['sale_price' => $content['sale_price']],
            );
        }
    }
}
