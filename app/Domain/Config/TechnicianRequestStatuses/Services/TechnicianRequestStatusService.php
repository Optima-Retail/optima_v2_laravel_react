<?php

declare(strict_types=1);

namespace App\Domain\Config\TechnicianRequestStatuses\Services;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use App\Models\TechnicianRequestStatus;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TechnicianRequestStatusService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, kind?: string|null}  $filters
     * @return LengthAwarePaginator<int, TechnicianRequestStatus>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = trim((string) ($filters['kind'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'lifecycle', 'kind', 'is_open'], 'lifecycle');

        return TechnicianRequestStatus::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when(
                $kind !== '' && in_array($kind, TechnicianRequestStatusKind::values(), true),
                fn ($query) => $query->where('kind', $kind),
            )
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, kind?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (TechnicianRequestStatus $status): array => $this->toListItem($status));
    }

    /**
     * @param  array{kind: string, name: string, color?: string|null, lifecycle?: int|null, is_open: bool}  $data
     */
    public function create(array $data): TechnicianRequestStatus
    {
        return DB::transaction(function () use ($data): TechnicianRequestStatus {
            return TechnicianRequestStatus::query()->create([
                'kind' => $data['kind'],
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);
        });
    }

    /**
     * @param  array{kind: string, name: string, color?: string|null, lifecycle?: int|null, is_open: bool}  $data
     */
    public function update(TechnicianRequestStatus $status, array $data): TechnicianRequestStatus
    {
        return DB::transaction(function () use ($status, $data): TechnicianRequestStatus {
            $status->update([
                'kind' => $data['kind'],
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);

            return $status->fresh() ?? $status;
        });
    }

    public function delete(TechnicianRequestStatus $status): void
    {
        if ($status->trashed()) {
            return;
        }

        DB::transaction(function () use ($status): void {
            $status->delete();
        });
    }

    /**
     * @return array{id: int, kind: string, name: string, color: string|null, lifecycle: int|null, is_open: bool}
     */
    public function toFormData(TechnicianRequestStatus $status): array
    {
        return [
            'id' => $status->id,
            'kind' => $status->kind->value,
            'name' => $status->name,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
        ];
    }

    /**
     * @return array{id: int, kind: string, name: string, color: string|null, lifecycle: int|null, is_open: bool, created_at: string|null}
     */
    public function toListItem(TechnicianRequestStatus $status): array
    {
        return [
            'id' => $status->id,
            'kind' => $status->kind->value,
            'name' => $status->name,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'created_at' => $status->created_at?->toIso8601String(),
        ];
    }
}
