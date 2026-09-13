<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TechnicianRequestPriority;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class TechnicianRequestPriorityPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, TechnicianRequestPriority $technicianRequestPriority): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, TechnicianRequestPriority $technicianRequestPriority): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, TechnicianRequestPriority $technicianRequestPriority): bool
    {
        return $this->allows($user, 'delete');
    }
}
