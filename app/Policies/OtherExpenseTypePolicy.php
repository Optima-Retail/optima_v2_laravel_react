<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OtherExpenseType;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class OtherExpenseTypePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, OtherExpenseType $otherExpenseType): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, OtherExpenseType $otherExpenseType): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, OtherExpenseType $otherExpenseType): bool
    {
        return $this->allows($user, 'delete');
    }
}
