<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Estimates;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Requests\Web\WorkOrders\WorkOrderFormInput;
use App\Policies\EstimatePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(EstimatePolicy::class)->create($user);
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

        return array_merge(WorkOrderFormInput::baseRules(
            $owner->id,
            $service->accessibleCompanyIds($owner),
            WorkOrderStage::Estimate->value,
        ), [
            'stage' => ['required', 'string', 'in:'.WorkOrderStage::Estimate->value],
            'code' => ['nullable', 'string', 'max:64'],
            'status_id' => [
                'nullable',
                'integer',
                Rule::exists('work_order_statuses', 'id')
                    ->whereNull('deleted_at')
                    ->where('kind', WorkOrderStage::Estimate->value),
            ],
            'work_order_type_id' => [
                'required',
                'integer',
                Rule::exists('work_order_types', 'id')->whereNull('deleted_at'),
            ],
        ]);
    }
}
