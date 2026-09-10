<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\WorkOrderTypes;

use App\Models\WorkOrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWorkOrderTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrderType $workOrderType */
        $workOrderType = $this->route('work_order_type');

        return $this->user()?->can('update', $workOrderType) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => filled($this->input('code')) ? $this->input('code') : null,
            'color' => filled($this->input('color')) ? $this->input('color') : null,
            'service_type_ids' => array_values(array_filter(
                (array) $this->input('service_type_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var WorkOrderType $workOrderType */
        $workOrderType = $this->route('work_order_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('work_order_types', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($workOrderType->id),
            ],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'service_type_ids' => ['nullable', 'array'],
            'service_type_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('service_types', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
