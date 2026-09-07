<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Auth\Permissions\PolicyPermissionSync;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');
        $result = app(PolicyPermissionSync::class)->sync(prune: true);

        // Soft-delete legacy permission if present from earlier seeds.
        Permission::query()
            ->where('name', 'users.manage')
            ->where('guard_name', $guard)
            ->get()
            ->each(fn (Permission $permission) => $permission->softDeleteSafely());

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::findOrCreate(RoleEnum::Admin->value, $guard);
        $user = Role::findOrCreate(RoleEnum::User->value, $guard);

        $admin->syncPermissions($result['all']);
        $user->syncPermissions(
            array_values(array_filter(
                $result['all'],
                fn (string $permission): bool => $permission === 'users.view',
            )),
        );
    }
}
