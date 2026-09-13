<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\WorkOrders\Support\WorkOrderCompanyAccess;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

final class WorkOrderPolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && app(WorkOrderCompanyAccess::class)->hasActiveCompany($user);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isConfirmedWorkOrder()
            && $this->allows($user, 'view')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && app(WorkOrderCompanyAccess::class)->hasActiveCompany($user);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isConfirmedWorkOrder()
            && $this->allows($user, 'update')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isConfirmedWorkOrder()
            && $this->allows($user, 'delete')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function viewAttachments(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isConfirmedWorkOrder()
            && $this->allows($user, 'view-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function uploadAttachments(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isConfirmedWorkOrder()
            && $this->allows($user, 'upload-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function downloadAttachments(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isConfirmedWorkOrder()
            && $this->allows($user, 'download-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function deleteAttachments(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isConfirmedWorkOrder()
            && $this->allows($user, 'delete-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }
}
