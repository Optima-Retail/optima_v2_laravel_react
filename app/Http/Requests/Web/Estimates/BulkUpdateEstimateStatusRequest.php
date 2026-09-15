<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Estimates;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkUpdateEstimateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('estimates.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
            'status_id' => [
                'required',
                'integer',
                Rule::exists('work_order_statuses', 'id')->where(
                    fn ($query) => $query
                        ->where('kind', WorkOrderStage::Estimate->value)
                        ->whereNull('deleted_at'),
                ),
            ],
            'status_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
