<?php

declare(strict_types=1);

namespace App\Domain\Config\WorkOrderTypes\Services;

use App\Models\WorkOrderType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class WorkOrderTypeService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, WorkOrderType>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code'], 'name');

        return WorkOrderType::query()
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
            ->through(fn (WorkOrderType $type): array => $this->toListItem($type));
    }

    /**
     * @param  array{name: string, code?: string|null, color?: string|null}  $data
     */
    public function create(array $data): WorkOrderType
    {
        return DB::transaction(function () use ($data): WorkOrderType {
            return WorkOrderType::query()->create([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'color' => $data['color'] ?: null,
            ]);
        });
    }

    /**
     * @param  array{name: string, code?: string|null, color?: string|null}  $data
     */
    public function update(WorkOrderType $type, array $data): WorkOrderType
    {
        return DB::transaction(function () use ($type, $data): WorkOrderType {
            $type->update([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'color' => $data['color'] ?: null,
            ]);

            return $type->fresh();
        });
    }

    /**
     * Soft-delete a work order type. Records are never hard-deleted.
     */
    public function delete(WorkOrderType $type): void
    {
        if ($type->trashed()) {
            return;
        }

        DB::transaction(function () use ($type): void {
            $type->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string|null, color: string|null}
     */
    public function toFormData(WorkOrderType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'color' => $type->color,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string|null, color: string|null, created_at: string|null}
     */
    public function toListItem(WorkOrderType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'color' => $type->color,
            'created_at' => $type->created_at?->toIso8601String(),
        ];
    }
}
