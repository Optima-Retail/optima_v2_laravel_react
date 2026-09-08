<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Province;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class ProvincePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Province $province): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Province $province): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Province $province): bool
    {
        return $this->allows($user, 'delete');
    }
}
