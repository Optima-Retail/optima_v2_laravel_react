<?php

declare(strict_types=1);

namespace App\Domain\Config\ContractStatuses\Services;

use App\Models\ContractStatus;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ContractStatusService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, ContractStatus>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'lifecycle', 'is_open'], 'lifecycle');

        return ContractStatus::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
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
            ->through(fn (ContractStatus $status): array => $this->toListItem($status));
    }

    /**
     * @param  array{name: string, color?: string|null, lifecycle?: int|null, is_open: bool}  $data
     */
    public function create(array $data): ContractStatus
    {
        return DB::transaction(function () use ($data): ContractStatus {
            return ContractStatus::query()->create([
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);
        });
    }

    /**
     * @param  array{name: string, color?: string|null, lifecycle?: int|null, is_open: bool}  $data
     */
    public function update(ContractStatus $status, array $data): ContractStatus
    {
        return DB::transaction(function () use ($status, $data): ContractStatus {
            $status->update([
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);

            return $status->fresh();
        });
    }

    public function delete(ContractStatus $status): void
    {
        if ($status->trashed()) {
            return;
        }

        DB::transaction(function () use ($status): void {
            $status->delete();
        });
    }

    /**
     * @return array{id: int, name: string, color: string|null, lifecycle: int|null, is_open: bool}
     */
    public function toFormData(ContractStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
        ];
    }

    /**
     * @return array{id: int, name: string, color: string|null, lifecycle: int|null, is_open: bool, created_at: string|null}
     */
    public function toListItem(ContractStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'created_at' => $status->created_at?->toIso8601String(),
        ];
    }
}
