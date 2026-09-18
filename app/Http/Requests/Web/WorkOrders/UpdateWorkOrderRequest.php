<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\WorkOrders;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('work_order');

        return $this->user()?->can('update', $workOrder) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(WorkOrderFormInput::prepare($this));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());
        abort_if($owner === null, 403);

        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('work_order');
        $service = app(WorkOrderService::class);
        $stage = $workOrder->stage?->value ?? (string) $workOrder->stage;

        return array_merge(WorkOrderFormInput::baseRules(
            $owner->id,
            $service->accessibleCompanyIds($owner),
            $stage,
            $workOrder->contract_id !== null ? [(int) $workOrder->contract_id] : [],
            $workOrder->establishment_id !== null ? [(int) $workOrder->establishment_id] : [],
        ), [
            'code' => ['nullable', 'string', 'max:64'],
        ]);
    }
}
