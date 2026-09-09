<?php

declare(strict_types=1);

namespace App\Domain\Config\IncidentPriorities\Services;

use App\Models\IncidentPriority;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class IncidentPriorityService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, IncidentPriority>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'resolution_time_hours'], 'id');

        return IncidentPriority::query()
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
            ->through(fn (IncidentPriority $priority): array => $this->toListItem($priority));
    }

    /**
     * @param  array{name: string, color?: string|null, resolution_time_hours: int}  $data
     */
    public function create(array $data): IncidentPriority
    {
        return DB::transaction(function () use ($data): IncidentPriority {
            return IncidentPriority::query()->create([
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'resolution_time_hours' => $data['resolution_time_hours'],
            ]);
        });
    }

    /**
     * @param  array{name: string, color?: string|null, resolution_time_hours: int}  $data
     */
    public function update(IncidentPriority $priority, array $data): IncidentPriority
    {
        return DB::transaction(function () use ($priority, $data): IncidentPriority {
            $priority->update([
                'name' => $data['name'],
                'color' => $data['color'] ?: null,
                'resolution_time_hours' => $data['resolution_time_hours'],
            ]);

            return $priority->fresh();
        });
    }

    /**
     * Soft-delete a priority. Records are never hard-deleted.
     */
    public function delete(IncidentPriority $priority): void
    {
        if ($priority->trashed()) {
            return;
        }

        DB::transaction(function () use ($priority): void {
            $priority->delete();
        });
    }

    /**
     * @return array{id: int, name: string, color: string|null, resolution_time_hours: int}
     */
    public function toFormData(IncidentPriority $priority): array
    {
        return [
            'id' => $priority->id,
            'name' => $priority->name,
            'color' => $priority->color,
            'resolution_time_hours' => $priority->resolution_time_hours,
        ];
    }

    /**
     * @return array{id: int, name: string, color: string|null, resolution_time_hours: int, created_at: string|null}
     */
    public function toListItem(IncidentPriority $priority): array
    {
        return [
            'id' => $priority->id,
            'name' => $priority->name,
            'color' => $priority->color,
            'resolution_time_hours' => $priority->resolution_time_hours,
            'created_at' => $priority->created_at?->toIso8601String(),
        ];
    }
}
