<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\TechnicianRequests\Services\TechnicianRequestService;
use App\Models\TechnicianRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class TechnicianRequestPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, TechnicianRequest $technicianRequest): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $technicianRequest);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, TechnicianRequest $technicianRequest): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $technicianRequest);
    }

    public function delete(User $user, TechnicianRequest $technicianRequest): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $technicianRequest);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, TechnicianRequest $technicianRequest): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        return app(TechnicianRequestService::class)->canAccess($active, $technicianRequest);
    }
}
