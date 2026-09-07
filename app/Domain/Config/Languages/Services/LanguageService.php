<?php

declare(strict_types=1);

namespace App\Domain\Config\Languages\Services;

use App\Models\Language;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class LanguageService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Language>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code'], 'name');

        return Language::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
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
            ->through(fn (Language $language): array => $this->toListItem($language));
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): Language
    {
        return DB::transaction(function () use ($data): Language {
            return Language::query()->create([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
            ]);
        });
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function update(Language $language, array $data): Language
    {
        return DB::transaction(function () use ($language, $data): Language {
            $language->update([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
            ]);

            return $language->fresh();
        });
    }

    public function delete(Language $language): void
    {
        if ($language->trashed()) {
            return;
        }

        DB::transaction(function () use ($language): void {
            $language->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string}
     */
    public function toFormData(Language $language): array
    {
        return [
            'id' => $language->id,
            'name' => $language->name,
            'code' => $language->code,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, created_at: string|null}
     */
    public function toListItem(Language $language): array
    {
        return [
            'id' => $language->id,
            'name' => $language->name,
            'code' => $language->code,
            'created_at' => $language->created_at?->toIso8601String(),
        ];
    }
}
