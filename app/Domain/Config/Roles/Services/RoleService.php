<?php

declare(strict_types=1);

namespace App\Domain\Config\Roles\Services;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Auth\Permissions\PolicyPermissionDiscoverer;
use App\Domain\Auth\Permissions\PolicyPermissionSync;
use App\Models\Permission;
use App\Models\Role;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RoleService
{
    public function __construct(
        private readonly PolicyPermissionSync $permissionSync,
        private readonly PolicyPermissionDiscoverer $permissionDiscoverer,
    ) {}

    /**
     * @param  array{search?: string|null, type?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $adminRole = RoleEnum::Admin->value;
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name'], 'name');

        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($type === 'system', function ($query) use ($adminRole): void {
                $query->where('name', $adminRole);
            })
            ->when($type === 'custom', function ($query) use ($adminRole): void {
                $query->where('name', '!=', $adminRole);
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, type?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (Role $role): array => $this->toListItem($role));
    }

    /**
     * @param  array{name: string, permissions?: list<string>|null}  $data
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => config('auth.defaults.guard', 'web'),
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            return $role->load('permissions');
        });
    }

    /**
     * @param  array{name: string, permissions?: list<string>|null}  $data
     */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            if (! $this->isSystem($role)) {
                $role->name = $data['name'];
                $role->save();
            }

            $role->syncPermissions($data['permissions'] ?? []);

            return $role->fresh()->load('permissions');
        });
    }

    /**
     * Soft-delete a role. Records are never hard-deleted.
     */
    public function delete(Role $role): void
    {
        if ($role->trashed()) {
            return;
        }

        if ($this->isSystem($role)) {
            return;
        }

        DB::transaction(function () use ($role): void {
            $role->softDeleteSafely();
        });
    }

    public function syncDiscoveredPermissions(bool $prune = false): array
    {
        return $this->permissionSync->sync(prune: $prune);
    }

    /**
     * @return list<array{resource: string, permissions: list<string>}>
     */
    public function permissionGroups(): array
    {
        $guard = config('auth.defaults.guard', 'web');
        $discovered = $this->permissionDiscoverer->discover();

        /** @var Collection<int, string> $names */
        $names = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $discovered)
            ->orderBy('name')
            ->pluck('name');

        return $names
            ->groupBy(fn (string $name): string => strstr($name, '.', true) ?: $name)
            ->map(fn (Collection $permissions, string $resource): array => [
                'resource' => $resource,
                'permissions' => $permissions->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, permissions: list<string>, is_system: bool}
     */
    public function toFormData(Role $role): array
    {
        $role->loadMissing('permissions');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
            'is_system' => $this->isSystem($role),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     users_count: int,
     *     permissions_count: int,
     *     is_system: bool,
     *     permissions: list<string>
     * }
     */
    public function toListItem(Role $role): array
    {
        $role->loadMissing('permissions');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'users_count' => (int) ($role->users_count ?? 0),
            'permissions_count' => $role->permissions->count(),
            'is_system' => $this->isSystem($role),
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ];
    }

    public function isSystem(Role $role): bool
    {
        return $role->name === RoleEnum::Admin->value;
    }
}
