<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\ActiveCompany;
use App\Models\Contract;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class ContractPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $contract);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $contract);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $contract);
    }

    public function viewAttachments(User $user, Contract $contract): bool
    {
        return $this->allows($user, 'view-attachments') && $this->canAccess($user, $contract);
    }

    public function uploadAttachments(User $user, Contract $contract): bool
    {
        return $this->allows($user, 'upload-attachments') && $this->canAccess($user, $contract);
    }

    public function downloadAttachments(User $user, Contract $contract): bool
    {
        return $this->allows($user, 'download-attachments') && $this->canAccess($user, $contract);
    }

    public function deleteAttachments(User $user, Contract $contract): bool
    {
        return $this->allows($user, 'delete-attachments') && $this->canAccess($user, $contract);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, Contract $contract): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null || $contract->company_id === null) {
            return false;
        }

        if ($contract->company_id === $active->id) {
            return true;
        }

        return $active->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->where('related_company_id', $contract->company_id)
            ->exists();
    }
}
