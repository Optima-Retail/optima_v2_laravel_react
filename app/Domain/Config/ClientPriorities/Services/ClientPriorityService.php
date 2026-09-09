<?php

declare(strict_types=1);

namespace App\Domain\Config\ClientPriorities\Services;

use App\Models\ClientPriority;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ClientPriorityService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, ClientPriority>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'code', 'level'], 'level');

        return ClientPriority::query()
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
            ->through(fn (ClientPriority $priority): array => $this->toListItem($priority));
    }

    /**
     * @param  array{name: string, code?: string|null, color?: string|null, level: int}  $data
     */
    public function create(array $data): ClientPriority
    {
        return DB::transaction(function () use ($data): ClientPriority {
            return ClientPriority::query()->create([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'color' => $data['color'] ?: null,
                'level' => $data['level'],
            ]);
        });
    }

    /**
     * @param  array{name: string, code?: string|null, color?: string|null, level: int}  $data
     */
    public function update(ClientPriority $priority, array $data): ClientPriority
    {
        return DB::transaction(function () use ($priority, $data): ClientPriority {
            $priority->update([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'color' => $data['color'] ?: null,
                'level' => $data['level'],
            ]);

            return $priority->fresh();
        });
    }

    /**
     * Soft-delete a priority. Records are never hard-deleted.
     */
    public function delete(ClientPriority $priority): void
    {
        if ($priority->trashed()) {
            return;
        }

        DB::transaction(function () use ($priority): void {
            $priority->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, name: string, code: string|null, color: string|null, level: int}
     */
    public function toFormData(ClientPriority $priority): array
    {
        return [
            'id' => $priority->id,
            'name' => $priority->name,
            'code' => $priority->code,
            'color' => $priority->color,
            'level' => $priority->level,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string|null, color: string|null, level: int, created_at: string|null}
     */
    public function toListItem(ClientPriority $priority): array
    {
        return [
            'id' => $priority->id,
            'name' => $priority->name,
            'code' => $priority->code,
            'color' => $priority->color,
            'level' => $priority->level,
            'created_at' => $priority->created_at?->toIso8601String(),
        ];
    }
}
