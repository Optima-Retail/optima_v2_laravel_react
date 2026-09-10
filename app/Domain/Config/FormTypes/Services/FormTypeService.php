<?php

declare(strict_types=1);

namespace App\Domain\Config\FormTypes\Services;

use App\Models\FormType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class FormTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, FormType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name'], 'name');

        return FormType::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
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
            ->through(fn (FormType $type): array => $this->toListItem($type));
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): FormType
    {
        return DB::transaction(function () use ($data): FormType {
            return FormType::query()->create([
                'name' => $data['name'],
            ]);
        });
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(FormType $type, array $data): FormType
    {
        return DB::transaction(function () use ($type, $data): FormType {
            $type->update([
                'name' => $data['name'],
            ]);

            return $type->fresh();
        });
    }

    /**
     * Soft-delete a form type. Records are never hard-deleted.
     */
    public function delete(FormType $type): void
    {
        if ($type->trashed()) {
            return;
        }

        DB::transaction(function () use ($type): void {
            $type->delete();
        });
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toFormData(FormType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
        ];
    }

    /**
     * @return array{id: int, name: string, created_at: string|null}
     */
    public function toListItem(FormType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'created_at' => $type->created_at?->toIso8601String(),
        ];
    }
}
