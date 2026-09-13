<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Forms\Services\FormService;
use App\Models\Form;
use App\Models\User;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class FormPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && $this->hasActiveCompany($user);
    }

    public function view(User $user, Form $form): bool
    {
        return $this->allows($user, 'view') && $this->canAccess($user, $form);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && $this->hasActiveCompany($user);
    }

    public function update(User $user, Form $form): bool
    {
        return $this->allows($user, 'update') && $this->canAccess($user, $form);
    }

    public function delete(User $user, Form $form): bool
    {
        return $this->allows($user, 'delete') && $this->canAccess($user, $form);
    }

    private function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    private function canAccess(User $user, Form $form): bool
    {
        return app(FormService::class)->canAccess(
            app(ActiveCompany::class)->forUser($user),
            $form,
        );
    }
}
