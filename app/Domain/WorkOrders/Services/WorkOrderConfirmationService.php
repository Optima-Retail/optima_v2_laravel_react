<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Company;
use App\Models\TaskToPerform;
use App\Models\WorkOrder;
use InvalidArgumentException;

final class WorkOrderConfirmationService
{
    public function __construct(
        private readonly NumberingPatternService $numbering,
        private readonly WorkOrderStatusCatalog $statuses,
    ) {}

    public function confirm(WorkOrder $workOrder, ?int $workOrderStatusId = null, ?Company $owner = null): WorkOrder
    {
        if (! $workOrder->isEstimate()) {
            throw new InvalidArgumentException('Only an estimate can be confirmed as a work order.');
        }

        $workOrder->unsetRelation('status');

        $statusId = $workOrderStatusId ?? $this->statuses->postConfirmDefaultId();

        if ($statusId === null) {
            throw new InvalidArgumentException('Configure a post-confirm work-order status first.');
        }

        $payload = [
            'stage' => WorkOrderStage::WorkOrder,
            'status_id' => $statusId,
            'confirmed_at' => now(),
            'is_estimate' => true,
            'is_work_order' => true,
        ];

        if ($owner !== null) {
            $allocation = $this->allocateWorkOrderNumbering($owner);

            if ($allocation !== null) {
                if (filled($workOrder->work_order_num) && $workOrder->work_order_num !== $allocation['code']) {
                    $payload['work_order_old_num'] = $workOrder->work_order_num;
                }

                $payload['work_order_num'] = $allocation['code'];
                $payload['work_order_num_cardinal'] = $allocation['cardinal'];
                $payload['work_order_numbering_pattern_id'] = $allocation['numbering_pattern_id'];
                $payload['code'] = $allocation['code'];
            }
        }

        $workOrder->forceFill($payload)->save();

        // Same document id keeps tasks; flip type estimate → work_order (optima_back modelo change).
        TaskToPerform::query()
            ->where('document_id', $workOrder->id)
            ->where('document_type', TaskDocumentType::Estimate->value)
            ->update(['document_type' => TaskDocumentType::WorkOrder->value]);

        return $workOrder->refresh();
    }

    /**
     * @return array{code: string, cardinal: int, numbering_pattern_id: int}|null
     */
    private function allocateWorkOrderNumbering(Company $owner): ?array
    {
        $resource = NumberingResource::WorkOrders->value;
        $existing = $this->numbering->findForResource($owner, $resource);

        if ($existing !== null && ! $existing->is_active) {
            return null;
        }

        return $this->numbering->allocateNextDetails($owner, $resource);
    }
}
