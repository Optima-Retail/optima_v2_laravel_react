<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\ActiveCompany;
use App\Models\Evaluation;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class EvaluationPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $evaluation);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $evaluation);
    }

    public function delete(User $user, Evaluation $evaluation): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $evaluation);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, Evaluation $evaluation): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        $evaluation->loadMissing('establishment');
        $companyId = $evaluation->establishment?->company_id;

        if ($companyId === null) {
            return false;
        }

        if ((int) $companyId === (int) $active->id) {
            return true;
        }

        return $active->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->where('related_company_id', $companyId)
            ->exists();
    }
}
