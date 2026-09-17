<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\WorkOrders\Support\WorkOrderCompanyAccess;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\Concerns\ChecksDiscoveredPermissions;

/**
 * Permissions for estimate-identity work_orders rows (`is_estimate`).
 * Confirmed rows (also `is_work_order`) stay viewable as a read-only estimate screen;
 * mutations require current stage = estimate.
 */
final class EstimatePolicy
{
    use ChecksDiscoveredPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view') && app(WorkOrderCompanyAccess::class)->hasActiveCompany($user);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return (bool) $workOrder->is_estimate
            && $this->allows($user, 'view')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create') && app(WorkOrderCompanyAccess::class)->hasActiveCompany($user);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isEstimate()
            && $this->allows($user, 'update')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function updateClosed(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isEstimate()
            && $this->allows($user, 'update-closed')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isEstimate()
            && $this->allows($user, 'delete')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function viewAttachments(User $user, WorkOrder $workOrder): bool
    {
        return (bool) $workOrder->is_estimate
            && $this->allows($user, 'view-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function uploadAttachments(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isEstimate()
            && $this->allows($user, 'upload-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function downloadAttachments(User $user, WorkOrder $workOrder): bool
    {
        return (bool) $workOrder->is_estimate
            && $this->allows($user, 'download-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function deleteAttachments(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->isEstimate()
            && $this->allows($user, 'delete-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }

    public function viewPrivateAttachments(User $user, WorkOrder $workOrder): bool
    {
        return (bool) $workOrder->is_estimate
            && $this->allows($user, 'view-private-attachments')
            && app(WorkOrderCompanyAccess::class)->canAccess($user, $workOrder);
    }
}
