<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Models\NumberingPattern;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class NumberingPatternPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, NumberingPattern $numberingPattern): bool
    {
        return $this->allows($user, 'view') && $this->belongsToActiveCompany($user, $numberingPattern);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, NumberingPattern $numberingPattern): bool
    {
        return $this->allows($user, 'update') && $this->belongsToActiveCompany($user, $numberingPattern);
    }

    public function delete(User $user, NumberingPattern $numberingPattern): bool
    {
        return $this->allows($user, 'delete') && $this->belongsToActiveCompany($user, $numberingPattern);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function belongsToActiveCompany(User $user, NumberingPattern $numberingPattern): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        return $active !== null && (int) $numberingPattern->company_id === (int) $active->id;
    }
}
