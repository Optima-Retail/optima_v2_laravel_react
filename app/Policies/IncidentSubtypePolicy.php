<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IncidentSubtype;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class IncidentSubtypePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, IncidentSubtype $incidentSubtype): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, IncidentSubtype $incidentSubtype): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, IncidentSubtype $incidentSubtype): bool
    {
        return $this->allows($user, 'delete');
    }
}
