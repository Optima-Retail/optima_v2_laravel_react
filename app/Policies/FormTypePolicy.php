<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FormType;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class FormTypePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, FormType $formType): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, FormType $formType): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, FormType $formType): bool
    {
        return $this->allows($user, 'delete');
    }
}
