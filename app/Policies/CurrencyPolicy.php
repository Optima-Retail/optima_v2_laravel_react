<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class CurrencyPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $this->allows($user, 'delete');
    }
}
