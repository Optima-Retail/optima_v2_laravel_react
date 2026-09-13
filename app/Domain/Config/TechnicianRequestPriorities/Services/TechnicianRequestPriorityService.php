<?php

declare(strict_types=1);

namespace App\Domain\Config\TechnicianRequestPriorities\Services;

use App\Models\TechnicianRequestPriority;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TechnicianRequestPriorityService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, TechnicianRequestPriority>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'key'], 'id');

        return TechnicianRequestPriority::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%");
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
            ->through(fn (TechnicianRequestPriority $priority): array => $this->toListItem($priority));
    }

    /**
     * @param  array{name: string, key: string, color?: string|null}  $data
     */
    public function create(array $data): TechnicianRequestPriority
    {
        return DB::transaction(function () use ($data): TechnicianRequestPriority {
            return TechnicianRequestPriority::query()->create([
                'name' => $data['name'],
                'key' => $data['key'],
                'color' => $data['color'] ?: null,
            ]);
        });
    }

    /**
     * @param  array{name: string, key: string, color?: string|null}  $data
     */
    public function update(TechnicianRequestPriority $priority, array $data): TechnicianRequestPriority
    {
        return DB::transaction(function () use ($priority, $data): TechnicianRequestPriority {
            $priority->update([
                'name' => $data['name'],
                'key' => $data['key'],
                'color' => $data['color'] ?: null,
            ]);

            return $priority->fresh() ?? $priority;
        });
    }

    public function delete(TechnicianRequestPriority $priority): void
    {
        if ($priority->trashed()) {
            return;
        }

        DB::transaction(function () use ($priority): void {
            $priority->delete();
        });
    }

    /**
     * @return array{id: int, name: string, key: string, color: string|null}
     */
    public function toFormData(TechnicianRequestPriority $priority): array
    {
        return [
            'id' => $priority->id,
            'name' => $priority->name,
            'key' => $priority->key->value,
            'color' => $priority->color,
        ];
    }

    /**
     * @return array{id: int, name: string, key: string, color: string|null, created_at: string|null}
     */
    public function toListItem(TechnicianRequestPriority $priority): array
    {
        return [
            'id' => $priority->id,
            'name' => $priority->name,
            'key' => $priority->key->value,
            'color' => $priority->color,
            'created_at' => $priority->created_at?->toIso8601String(),
        ];
    }
}
