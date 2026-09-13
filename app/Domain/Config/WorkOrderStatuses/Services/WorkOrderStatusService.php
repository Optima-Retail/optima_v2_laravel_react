<?php

declare(strict_types=1);

namespace App\Domain\Config\WorkOrderStatuses\Services;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\WorkOrderStatus;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class WorkOrderStatusService
{
    /**
     * @param  array{search?: string|null, kind?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, WorkOrderStatus>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = trim((string) ($filters['kind'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'kind', 'lifecycle', 'is_open'], 'lifecycle');

        return WorkOrderStatus::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($kind !== '' && in_array($kind, WorkOrderStage::values(), true), function ($query) use ($kind): void {
                $query->where('kind', $kind);
            })
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, kind?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (WorkOrderStatus $status): array => $this->toListItem($status));
    }

    /**
     * @param  array{name: string, kind: string, color?: string|null, lifecycle?: int|null, is_open: bool}  $data
     */
    public function create(array $data): WorkOrderStatus
    {
        return DB::transaction(function () use ($data): WorkOrderStatus {
            return WorkOrderStatus::query()->create([
                'name' => $data['name'],
                'kind' => $data['kind'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);
        });
    }

    /**
     * @param  array{name: string, kind: string, color?: string|null, lifecycle?: int|null, is_open: bool}  $data
     */
    public function update(WorkOrderStatus $status, array $data): WorkOrderStatus
    {
        return DB::transaction(function () use ($status, $data): WorkOrderStatus {
            $status->update([
                'name' => $data['name'],
                'kind' => $data['kind'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);

            return $status->fresh() ?? $status;
        });
    }

    public function delete(WorkOrderStatus $status): void
    {
        if ($status->trashed()) {
            return;
        }

        DB::transaction(function () use ($status): void {
            $status->delete();
        });
    }

    /**
     * @return array{id: int, name: string, kind: string, color: string|null, lifecycle: int|null, is_open: bool}
     */
    public function toFormData(WorkOrderStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'kind' => $status->kind instanceof WorkOrderStage ? $status->kind->value : (string) $status->kind,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
        ];
    }

    /**
     * @return array{id: int, name: string, kind: string, color: string|null, lifecycle: int|null, is_open: bool, created_at: string|null}
     */
    public function toListItem(WorkOrderStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'kind' => $status->kind instanceof WorkOrderStage ? $status->kind->value : (string) $status->kind,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'created_at' => $status->created_at?->toIso8601String(),
        ];
    }
}
