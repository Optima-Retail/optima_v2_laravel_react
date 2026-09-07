<?php

declare(strict_types=1);

namespace App\Domain\Config\Teams\Services;

use App\Models\Team;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TeamService
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Team>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'code', 'name'], 'name');

        return Team::query()
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
            ->through(fn (Team $team): array => $this->toListItem($team));
    }

    /**
     * @param  array{code: string, name: string}  $data
     */
    public function create(array $data): Team
    {
        return DB::transaction(function () use ($data): Team {
            return Team::query()->create([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'manager_id' => null,
                'controller_id' => null,
            ]);
        });
    }

    /**
     * @param  array{code: string, name: string}  $data
     */
    public function update(Team $team, array $data): Team
    {
        return DB::transaction(function () use ($team, $data): Team {
            $team->update([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
            ]);

            return $team->fresh();
        });
    }

    /**
     * Soft-delete a team. Records are never hard-deleted.
     */
    public function delete(Team $team): void
    {
        if ($team->trashed()) {
            return;
        }

        DB::transaction(function () use ($team): void {
            $team->softDeleteSafely();
        });
    }

    /**
     * @return array{id: int, code: string, name: string, manager_id: int|null, controller_id: int|null}
     */
    public function toFormData(Team $team): array
    {
        return [
            'id' => $team->id,
            'code' => $team->code,
            'name' => $team->name,
            'manager_id' => $team->manager_id,
            'controller_id' => $team->controller_id,
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, manager_id: int|null, controller_id: int|null, created_at: string|null}
     */
    public function toListItem(Team $team): array
    {
        return [
            'id' => $team->id,
            'code' => $team->code,
            'name' => $team->name,
            'manager_id' => $team->manager_id,
            'controller_id' => $team->controller_id,
            'created_at' => $team->created_at?->toIso8601String(),
        ];
    }
}
