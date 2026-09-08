<?php

declare(strict_types=1);

namespace App\Domain\Config\FieldHelps\Services;

use App\Models\FieldHelp;
use App\Support\ListQuery;
use App\Support\Locale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FieldHelpService
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, FieldHelp>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'key', 'context', 'is_active', 'sort_order', 'created_at'],
            'key',
        );

        return FieldHelp::query()
            ->with('translations')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('key', 'like', "%{$search}%")
                        ->orWhere('context', 'like', "%{$search}%")
                        ->orWhereHas('translations', function ($translations) use ($search): void {
                            $translations
                                ->where('title', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
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
            ->through(fn (FieldHelp $fieldHelp): array => $this->toListItem($fieldHelp));
    }

    /**
     * @param  list<string>|array<int, string>  $keys
     * @return array<string, array{title: string|null, description: string|null}>
     */
    public function getForKeys(array $keys, ?string $locale = null): array
    {
        $normalizedKeys = $this->normalizeKeys($keys);
        if ($normalizedKeys === []) {
            return [];
        }

        $locale = Locale::normalize($locale ?? app()->getLocale());
        $fallbackLocale = Locale::normalize((string) config('app.fallback_locale', Locale::DEFAULT));

        $catalog = $this->catalogForLocale($locale, $fallbackLocale);

        $result = [];
        foreach ($normalizedKeys as $key) {
            if (isset($catalog[$key])) {
                $result[$key] = $catalog[$key];
            }
        }

        return $result;
    }

    /**
     * @param  array{
     *     key: string,
     *     context?: string|null,
     *     is_active?: bool,
     *     sort_order?: int,
     *     translations: list<array{locale: string, title?: string|null, description?: string|null}>|array<string, array{title?: string|null, description?: string|null, example?: string|null}>
     * }  $data
     */
    public function create(array $data): FieldHelp
    {
        $fieldHelp = DB::transaction(function () use ($data): FieldHelp {
            $fieldHelp = FieldHelp::query()->create([
                'key' => $this->normalizeKey($data['key']),
                'context' => $data['context'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncTranslations($fieldHelp, $data['translations'] ?? []);

            return $fieldHelp->load('translations');
        });

        $this->forgetCache();

        return $fieldHelp;
    }

    /**
     * @param  array{
     *     key?: string,
     *     context?: string|null,
     *     is_active?: bool,
     *     sort_order?: int,
     *     translations?: list<array{locale: string, title?: string|null, description?: string|null}>|array<string, array{title?: string|null, description?: string|null, example?: string|null}>
     * }  $data
     */
    public function update(FieldHelp $fieldHelp, array $data): FieldHelp
    {
        $updated = DB::transaction(function () use ($fieldHelp, $data): FieldHelp {
            $payload = [];
            if (array_key_exists('key', $data)) {
                $payload['key'] = $this->normalizeKey((string) $data['key']);
            }
            if (array_key_exists('context', $data)) {
                $payload['context'] = $data['context'];
            }
            if (array_key_exists('is_active', $data)) {
                $payload['is_active'] = (bool) $data['is_active'];
            }
            if (array_key_exists('sort_order', $data)) {
                $payload['sort_order'] = (int) $data['sort_order'];
            }

            if ($payload !== []) {
                $fieldHelp->update($payload);
            }

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations($fieldHelp, $data['translations'] ?? []);
            }

            return $fieldHelp->fresh(['translations']) ?? $fieldHelp;
        });

        $this->forgetCache();

        return $updated;
    }

    public function delete(FieldHelp $fieldHelp): void
    {
        DB::transaction(function () use ($fieldHelp): void {
            $fieldHelp->delete();
        });

        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        foreach (Locale::supported() as $locale) {
            Cache::forget($this->cacheKey($locale));
        }
    }

    public function normalizeKey(string $key): string
    {
        return Str::lower(trim($key));
    }

    public function buildKey(string $table, string $column): string
    {
        return $this->normalizeKey($table).'.'.$this->normalizeKey($column);
    }

    /**
     * @return array{table: string|null, column: string|null}
     */
    public function parseKey(string $key): array
    {
        $normalized = $this->normalizeKey($key);
        $parts = explode('.', $normalized, 2);

        if (count($parts) !== 2) {
            return ['table' => null, 'column' => null];
        }

        return [
            'table' => $parts[0],
            'column' => $parts[1],
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     key: string,
     *     table: string|null,
     *     column: string|null,
     *     context: string|null,
     *     is_active: bool,
     *     translations: list<array{locale: string, title: string, description: string}>
     * }
     */
    public function toFormData(FieldHelp $fieldHelp): array
    {
        $fieldHelp->loadMissing('translations');
        $parsed = $this->parseKey($fieldHelp->key);

        $translations = $fieldHelp->translations
            ->sortBy('locale')
            ->values()
            ->map(fn ($row): array => [
                'locale' => $row->locale,
                'title' => $row->title ?? '',
                'description' => $row->description ?? '',
            ])
            ->all();

        return [
            'id' => $fieldHelp->id,
            'key' => $fieldHelp->key,
            'table' => $parsed['table'],
            'column' => $parsed['column'],
            'context' => $fieldHelp->context,
            'is_active' => $fieldHelp->is_active,
            'translations' => $translations,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     key: string,
     *     context: string|null,
     *     is_active: bool,
     *     title: string|null,
     *     created_at: string|null
     * }
     */
    public function toListItem(FieldHelp $fieldHelp): array
    {
        $fieldHelp->loadMissing('translations');
        $locale = Locale::normalize(app()->getLocale());
        $fallback = Locale::normalize((string) config('app.fallback_locale', Locale::DEFAULT));
        $translation = $fieldHelp->translations->firstWhere('locale', $locale)
            ?? $fieldHelp->translations->firstWhere('locale', $fallback);

        return [
            'id' => $fieldHelp->id,
            'key' => $fieldHelp->key,
            'context' => $fieldHelp->context,
            'is_active' => $fieldHelp->is_active,
            'title' => $translation?->title,
            'created_at' => $fieldHelp->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>|array<int, string>  $keys
     * @return list<string>
     */
    public function normalizeKeys(array $keys): array
    {
        $normalized = [];
        foreach ($keys as $key) {
            if (! is_string($key)) {
                continue;
            }

            $value = $this->normalizeKey($key);
            if ($value === '' || ! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $value)) {
                continue;
            }

            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * @return array<string, array{title: string|null, description: string|null}>
     */
    private function catalogForLocale(string $locale, string $fallbackLocale): array
    {
        return Cache::remember(
            $this->cacheKey($locale),
            self::CACHE_TTL_SECONDS,
            function () use ($locale, $fallbackLocale): array {
                $helps = FieldHelp::query()
                    ->where('is_active', true)
                    ->with('translations')
                    ->orderBy('sort_order')
                    ->orderBy('key')
                    ->get();

                $catalog = [];
                foreach ($helps as $help) {
                    $translation = $help->translations->firstWhere('locale', $locale)
                        ?? $help->translations->firstWhere('locale', $fallbackLocale);

                    if ($translation === null) {
                        continue;
                    }

                    $payload = [
                        'title' => $translation->title,
                        'description' => $translation->description,
                    ];

                    if ($payload['title'] === null && $payload['description'] === null) {
                        continue;
                    }

                    $catalog[$help->key] = $payload;
                }

                return $catalog;
            },
        );
    }

    private function cacheKey(string $locale): string
    {
        return "field-help:locale:{$locale}";
    }

    /**
     * @param  list<array{locale: string, title?: string|null, description?: string|null}>|array<string, array{title?: string|null, description?: string|null, example?: string|null}>  $translations
     */
    private function syncTranslations(FieldHelp $fieldHelp, array $translations): void
    {
        $normalized = $this->normalizeTranslationInput($translations);
        $keepLocales = array_keys($normalized);

        $fieldHelp->translations()
            ->when(
                $keepLocales === [],
                fn ($query) => $query->delete(),
                fn ($query) => $query->whereNotIn('locale', $keepLocales)->delete(),
            );

        foreach ($normalized as $locale => $content) {
            $fieldHelp->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $content['title'] ?? null,
                    'description' => $content['description'] ?? null,
                    'example' => null,
                ],
            );
        }
    }

    /**
     * @param  list<array{locale?: string, title?: string|null, description?: string|null}>|array<string, array{title?: string|null, description?: string|null, example?: string|null}>  $translations
     * @return array<string, array{title: string|null, description: string|null}>
     */
    private function normalizeTranslationInput(array $translations): array
    {
        $normalized = [];

        $isList = array_is_list($translations);

        foreach ($translations as $key => $content) {
            if (! is_array($content)) {
                continue;
            }

            $locale = $isList
                ? (string) ($content['locale'] ?? '')
                : (string) $key;

            if (! Locale::isSupported($locale)) {
                continue;
            }

            $normalized[$locale] = [
                'title' => $content['title'] ?? null,
                'description' => $content['description'] ?? null,
            ];
        }

        return $normalized;
    }
}
