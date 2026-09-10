<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class PaymentMethodPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, PaymentMethod $model): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, PaymentMethod $model): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, PaymentMethod $model): bool
    {
        return $this->allows($user, 'delete');
    }
}
