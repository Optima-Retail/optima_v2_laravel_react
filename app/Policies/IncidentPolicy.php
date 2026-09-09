<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Incidents\Services\IncidentService;
use App\Models\Establishment;
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

        $service = app(IncidentService::class);
        $companyIds = $service->accessibleCompanyIds($active);

        if ($companyIds === []) {
            return false;
        }

        $incident->loadMissing(['establishment', 'type']);

        if ($incident->establishment_id !== null) {
            $companyId = $incident->establishment?->company_id;

            return $companyId !== null && in_array((int) $companyId, $companyIds, true);
        }

        if ($incident->origin_type === 'company' && $incident->origin_id !== null) {
            return in_array((int) $incident->origin_id, $companyIds, true);
        }

        if ($incident->origin_type === 'brand' && $incident->origin_id !== null) {
            return in_array((int) $incident->origin_id, $service->accessibleBrandIds($companyIds), true);
        }

        if ($incident->origin_type === 'establishment' && $incident->origin_id !== null) {
            $companyId = Establishment::query()
                ->whereKey($incident->origin_id)
                ->value('company_id');

            return $companyId !== null && in_array((int) $companyId, $companyIds, true);
        }

        if ($incident->origin_type === null && $incident->type?->origin_required === false) {
            return true;
        }

        return false;
    }
}
