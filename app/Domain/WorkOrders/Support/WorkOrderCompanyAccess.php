<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Support;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\ActiveCompany;
use App\Models\User;
use App\Models\WorkOrder;

final class WorkOrderCompanyAccess
{
    public function hasActiveCompany(User $user): bool
    {
        return app(ActiveCompany::class)->forUser($user) !== null;
    }

    public function canAccess(User $user, WorkOrder $workOrder): bool
    {
        $active = app(ActiveCompany::class)->forUser($user);

        if ($active === null) {
            return false;
        }

        $workOrder->loadMissing('establishment');
        $companyId = $workOrder->establishment?->company_id;

        if ($companyId === null) {
            return false;
        }

        if ((int) $companyId === (int) $active->id) {
            return true;
        }

        return $active->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->where('related_company_id', $companyId)
            ->exists();
    }
}
