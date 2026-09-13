<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Incidents\Services\IncidentService;
use App\Models\Incident;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class IncidentPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, Incident $incident): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $incident);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, Incident $incident): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $incident);
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $incident);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, Incident $incident): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        return app(IncidentService::class)->canAccess($active, $incident);
    }
}
