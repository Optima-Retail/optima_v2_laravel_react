<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Domain\Auth\Permissions\PolicyPermissionDiscoverer;
use App\Models\User;

trait ChecksDiscoveredPermissions
{
    protected function allows(User $user, string $ability): bool
    {
        $permission = app(PolicyPermissionDiscoverer::class)
            ->permissionName(static::class, $ability);

        return $user->can($permission);
    }
}
