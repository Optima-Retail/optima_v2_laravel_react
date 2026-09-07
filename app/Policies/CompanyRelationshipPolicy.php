<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Models\CompanyRelationship;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class CompanyRelationshipPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, CompanyRelationship $companyRelationship): bool
    {
        return $this->allows($user, 'view') && $this->owns($user, $companyRelationship);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, CompanyRelationship $companyRelationship): bool
    {
        return $this->allows($user, 'update') && $this->owns($user, $companyRelationship);
    }

    public function delete(User $user, CompanyRelationship $companyRelationship): bool
    {
        return $this->allows($user, 'delete') && $this->owns($user, $companyRelationship);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function owns(User $user, CompanyRelationship $relationship): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        return $active !== null && $relationship->owner_company_id === $active->id;
    }
}
