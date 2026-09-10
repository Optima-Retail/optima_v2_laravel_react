<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaskToPerform;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class TaskToPerformPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, TaskToPerform $taskToPerform): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, TaskToPerform $taskToPerform): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, TaskToPerform $taskToPerform): bool
    {
        return $this->allows($user, 'delete');
    }
}
