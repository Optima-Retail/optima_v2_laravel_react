<?php

declare(strict_types=1);

namespace App\Domain\Config\IncidentStatuses\Services;

use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class IncidentStatusService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, IncidentStatus>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'lifecycle', 'is_open'], 'lifecycle');

        return IncidentStatus::query()
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
            ->through(fn (IncidentStatus $status): array => $this->toListItem($status));
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function incidentTypeOptions(): array
    {
        return IncidentType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (IncidentType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
                'color' => $type->color,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, color?: string|null, lifecycle?: int|null, is_open: bool, excluded_type_ids?: list<int>}  $data
     */
    public function create(array $data): IncidentStatus
    {
        return DB::transaction(function () use ($data): IncidentStatus {
            $status = IncidentStatus::query()->create([
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);

            $status->excludedTypes()->sync($data['excluded_type_ids'] ?? []);

            return $status->fresh(['excludedTypes']) ?? $status;
        });
    }

    /**
     * @param  array{name: string, color?: string|null, lifecycle?: int|null, is_open: bool, excluded_type_ids?: list<int>}  $data
     */
    public function update(IncidentStatus $status, array $data): IncidentStatus
    {
        return DB::transaction(function () use ($status, $data): IncidentStatus {
            $status->update([
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'lifecycle' => $data['lifecycle'] ?? null,
                'is_open' => (bool) $data['is_open'],
            ]);

            $status->excludedTypes()->sync($data['excluded_type_ids'] ?? []);

            return $status->fresh(['excludedTypes']) ?? $status;
        });
    }

    public function delete(IncidentStatus $status): void
    {
        if ($status->trashed()) {
            return;
        }

        DB::transaction(function () use ($status): void {
            $status->excludedTypes()->detach();
            $status->delete();
        });
    }

    /**
     * @return array{id: int, name: string, color: string|null, lifecycle: int|null, is_open: bool, excluded_type_ids: list<int>}
     */
    public function toFormData(IncidentStatus $status): array
    {
        $status->loadMissing('excludedTypes');

        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'lifecycle' => $status->lifecycle,
            'is_open' => $status->is_open,
            'excluded_type_ids' => $status->excludedTypes
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, color: string|null, lifecycle: int|null, is_open: bool, created_at: string|null}
     */
    public function toListItem(IncidentStatus $status): array
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
