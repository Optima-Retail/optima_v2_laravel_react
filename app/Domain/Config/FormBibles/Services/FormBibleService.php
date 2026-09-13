<?php

declare(strict_types=1);

namespace App\Domain\Config\FormBibles\Services;

use App\Models\FormBible;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class FormBibleService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name'], 'name');

        return FormBible::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (FormBible $bible): array => [
                'id' => $bible->id,
                'name' => $bible->name,
                'created_at' => $bible->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ]);
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): FormBible
    {
        return DB::transaction(fn (): FormBible => FormBible::query()->create(['name' => $data['name']]));
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(FormBible $bible, array $data): FormBible
    {
        return DB::transaction(function () use ($bible, $data): FormBible {
            $bible->update(['name' => $data['name']]);

            return $bible->fresh() ?? $bible;
        });
    }

    public function delete(FormBible $bible): void
    {
        if ($bible->trashed()) {
            return;
        }

        DB::transaction(fn () => $bible->delete());
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toFormData(FormBible $bible): array
    {
        return ['id' => $bible->id, 'name' => $bible->name];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function options(): array
    {
        return FormBible::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FormBible $bible): array => ['id' => $bible->id, 'label' => $bible->name])
            ->values()
            ->all();
    }
}
