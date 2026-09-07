<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class RolePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->name === RoleEnum::Admin->value) {
            return false;
        }

        return $this->allows($user, 'delete');
    }
}
