<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FieldHelp;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

/**
 * Admin CRUD readiness. Runtime resolve endpoint is auth-only (global help).
 */
final class FieldHelpPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, FieldHelp $fieldHelp): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, FieldHelp $fieldHelp): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, FieldHelp $fieldHelp): bool
    {
        return $this->allows($user, 'delete');
    }
}
