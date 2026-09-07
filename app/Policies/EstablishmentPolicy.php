<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Models\Establishment;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class EstablishmentPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, Establishment $establishment): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $establishment);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, Establishment $establishment): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $establishment);
    }

    public function delete(User $user, Establishment $establishment): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $establishment);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, Establishment $establishment): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        if ($establishment->company_id === $active->id) {
            return true;
        }

        return $active->ownedRelationships()
            ->where('related_company_id', $establishment->company_id)
            ->exists();
    }
}
