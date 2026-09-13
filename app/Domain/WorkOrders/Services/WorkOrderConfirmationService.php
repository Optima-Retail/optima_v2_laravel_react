<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Company;
use App\Models\WorkOrder;
use InvalidArgumentException;

final class WorkOrderConfirmationService
{
    public function __construct(
        private readonly NumberingPatternService $numbering,
    ) {}

    public function confirm(WorkOrder $workOrder, ?int $workOrderStatusId = null, ?Company $owner = null): WorkOrder
    {
        if (! $workOrder->isEstimate()) {
            throw new InvalidArgumentException('Only an estimate can be confirmed as a work order.');
        }

        $workOrder->unsetRelation('status');

        $payload = [
            'stage' => WorkOrderStage::WorkOrder,
            'status_id' => $workOrderStatusId ?? WorkOrder::DEFAULT_CONFIRMED_STATUS_ID,
            'confirmed_at' => now(),
        ];

        if ($owner !== null) {
            $otCode = $this->allocateWorkOrderCode($owner);

            if ($otCode !== null) {
                $payload['code'] = $otCode;
            }
        }

        $subject = trim((string) $workOrder->subject);
        $fromCode = $workOrder->code ?: (string) $workOrder->id;

        if ($subject !== '' && ! str_contains($subject, '(viene de ')) {
            $payload['subject'] = $subject.' (viene de '.$fromCode.')';
        }

        $workOrder->forceFill($payload)->save();

        return $workOrder->refresh();
    }

    private function allocateWorkOrderCode(Company $owner): ?string
    {
        $resource = NumberingResource::WorkOrders->value;
        $existing = $this->numbering->findForResource($owner, $resource);

        if ($existing !== null && ! $existing->is_active) {
            return null;
        }

        $allocated = $this->numbering->allocateNext($owner, $resource);

        return is_string($allocated) && $allocated !== '' ? $allocated : null;
    }
}
