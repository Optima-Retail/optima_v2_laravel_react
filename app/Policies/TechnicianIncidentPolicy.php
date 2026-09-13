<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Models\TechnicianIncident;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class TechnicianIncidentPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, TechnicianIncident $technicianIncident): bool
    {
        return $this->allows($user, 'view') && $this->owns($user, $technicianIncident);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, TechnicianIncident $technicianIncident): bool
    {
        return $this->allows($user, 'update') && $this->owns($user, $technicianIncident);
    }

    public function delete(User $user, TechnicianIncident $technicianIncident): bool
    {
        return $this->allows($user, 'delete') && $this->owns($user, $technicianIncident);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function owns(User $user, TechnicianIncident $incident): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        $incident->loadMissing('technician');

        return $incident->technician !== null
            && (int) $incident->technician->owner_company_id === (int) $active->id;
    }
}
