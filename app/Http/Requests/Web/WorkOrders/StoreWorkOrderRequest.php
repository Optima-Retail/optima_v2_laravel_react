<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\WorkOrders;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkOrder::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_merge(WorkOrderFormInput::prepare($this), [
            'stage' => WorkOrderStage::WorkOrder->value,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());
        abort_if($owner === null, 403);

        $service = app(WorkOrderService::class);
        $establishmentIds = array_column($service->establishmentOptions($owner), 'id');
        $establishmentIds = $establishmentIds === [] ? [0] : $establishmentIds;

        return array_merge(WorkOrderFormInput::baseRules(
            $owner->id,
            $establishmentIds,
            WorkOrderStage::WorkOrder->value,
        ), [
            'stage' => ['required', 'string', 'in:'.WorkOrderStage::WorkOrder->value],
            'code' => ['nullable', 'string', 'max:64'],
        ]);
    }
}
