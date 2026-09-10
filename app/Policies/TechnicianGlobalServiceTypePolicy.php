<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TechnicianGlobalServiceType;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class TechnicianGlobalServiceTypePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, TechnicianGlobalServiceType $technicianGlobalServiceType): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, TechnicianGlobalServiceType $technicianGlobalServiceType): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, TechnicianGlobalServiceType $technicianGlobalServiceType): bool
    {
        return $this->allows($user, 'delete');
    }
}
