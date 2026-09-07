<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class BrandPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'delete');
    }

    public function viewMessages(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'view-messages');
    }

    public function sendMessages(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'send-messages');
    }

    public function viewMessageFiles(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'view-message-files');
    }

    public function downloadMessageFiles(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'download-message-files');
    }

    public function sendMessageFiles(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'send-message-files');
    }
}
