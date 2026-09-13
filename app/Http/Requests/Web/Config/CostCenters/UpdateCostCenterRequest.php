<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\CostCenters;

use App\Models\CostCenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CostCenter $costCenter */
        $costCenter = $this->route('cost_center');

        return $this->user()?->can('update', $costCenter) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CostCenter $costCenter */
        $costCenter = $this->route('cost_center');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('cost_centers', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($costCenter->id),
            ],
        ];
    }
}
