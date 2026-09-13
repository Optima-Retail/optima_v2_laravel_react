<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Compliments\Services\ComplimentService;
use App\Models\Compliment;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class ComplimentPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, Compliment $compliment): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $compliment);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, Compliment $compliment): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $compliment);
    }

    public function delete(User $user, Compliment $compliment): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $compliment);
    }

    public function viewAttachments(User $user, Compliment $compliment): bool
    {
        return $this->allows($user, 'view-attachments') && $this->canAccess($user, $compliment);
    }

    public function uploadAttachments(User $user, Compliment $compliment): bool
    {
        return $this->allows($user, 'upload-attachments') && $this->canAccess($user, $compliment);
    }

    public function downloadAttachments(User $user, Compliment $compliment): bool
    {
        return $this->allows($user, 'download-attachments') && $this->canAccess($user, $compliment);
    }

    public function deleteAttachments(User $user, Compliment $compliment): bool
    {
        return $this->allows($user, 'delete-attachments') && $this->canAccess($user, $compliment);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, Compliment $compliment): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        return app(ComplimentService::class)->canAccess($active, $compliment);
    }
}
