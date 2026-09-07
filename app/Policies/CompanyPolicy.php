<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class CompanyPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Company $company): bool
    {
        return $this->allows($user, 'view') && $user->belongsToCompany($company->id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Company $company): bool
    {
        return $this->allows($user, 'update') && $user->belongsToCompany($company->id);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->allows($user, 'delete') && $user->belongsToCompany($company->id);
    }
}
