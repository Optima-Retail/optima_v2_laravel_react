<?php

declare(strict_types=1);

namespace App\Domain\Auth\Permissions;

use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class PolicyPermissionSync
{
    public function __construct(
        private readonly PolicyPermissionDiscoverer $discoverer,
    ) {}

    /**
     * Create permissions discovered from policies and optionally soft-delete stale ones.
     *
     * @return array{created: list<string>, existing: list<string>, pruned: list<string>, all: list<string>}
     */
    public function sync(bool $prune = false): array
    {
        $guard = config('auth.defaults.guard', 'web');
        $discovered = $this->discoverer->discover();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $created = [];
        $existing = [];

        foreach ($discovered as $name) {
            $permission = Permission::withTrashed()
                ->where('guard_name', $guard)
                ->where('name', $name)
                ->first();

            if ($permission?->trashed()) {
                $permission->restore();
                $existing[] = $name;

                continue;
            }

            if ($permission) {
                $existing[] = $name;

                continue;
            }

            Permission::query()->create([
                'name' => $name,
                'guard_name' => $guard,
            ]);
            $created[] = $name;
        }

        $pruned = [];

        if ($prune) {
            $stale = Permission::query()
                ->where('guard_name', $guard)
                ->whereNotIn('name', $discovered)
                ->get();

            foreach ($stale as $permission) {
                $pruned[] = $permission->name;
                $permission->softDeleteSafely();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return [
            'created' => $created,
            'existing' => $existing,
            'pruned' => $pruned,
            'all' => $discovered,
        ];
    }
}
