<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Forms\Services\FormTemplateService;
use App\Models\FormTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class FormTemplatePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, FormTemplate $formTemplate): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $formTemplate);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, FormTemplate $formTemplate): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $formTemplate);
    }

    public function delete(User $user, FormTemplate $formTemplate): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $formTemplate);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, FormTemplate $formTemplate): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        return app(FormTemplateService::class)->canAccess($active, $formTemplate);
    }
}
