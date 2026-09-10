<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class VehiclePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->allows($user, 'delete');
    }
}
