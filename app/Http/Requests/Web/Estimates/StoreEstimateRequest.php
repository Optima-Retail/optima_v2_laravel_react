<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Estimates;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Requests\Web\WorkOrders\WorkOrderFormInput;
use App\Policies\EstimatePolicy;
use Illuminate\Foundation\Http\FormRequest;

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
        $establishmentIds = array_column($service->establishmentOptions($owner), 'id');
        $establishmentIds = $establishmentIds === [] ? [0] : $establishmentIds;

        return array_merge(WorkOrderFormInput::baseRules(
            $owner->id,
            $establishmentIds,
            WorkOrderStage::Estimate->value,
        ), [
            'stage' => ['required', 'string', 'in:'.WorkOrderStage::Estimate->value],
            'code' => ['nullable', 'string', 'max:64'],
        ]);
    }
}
