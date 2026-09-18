<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Estimates;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Requests\Web\WorkOrders\WorkOrderFormInput;
use App\Models\WorkOrder;
use App\Policies\EstimatePolicy;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder $estimate */
        $estimate = $this->route('estimate');
        $user = $this->user();

        return $user !== null
            && $estimate instanceof WorkOrder
            && app(EstimatePolicy::class)->update($user, $estimate);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_merge(WorkOrderFormInput::prepare($this), [
            'stage' => WorkOrderStage::Estimate->value,
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
        /** @var WorkOrder $estimate */
        $estimate = $this->route('estimate');

        return array_merge(WorkOrderFormInput::baseRules(
            $owner->id,
            $service->accessibleCompanyIds($owner),
            WorkOrderStage::Estimate->value,
            $estimate->contract_id !== null ? [(int) $estimate->contract_id] : [],
            $estimate->establishment_id !== null ? [(int) $estimate->establishment_id] : [],
        ), [
            'code' => ['nullable', 'string', 'max:64'],
        ]);
    }
}
