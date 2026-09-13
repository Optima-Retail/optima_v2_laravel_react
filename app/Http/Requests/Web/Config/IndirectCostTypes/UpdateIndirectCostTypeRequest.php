<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\IndirectCostTypes;

use App\Models\IndirectCostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIndirectCostTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IndirectCostType $indirectCostType */
        $indirectCostType = $this->route('indirect_cost_type');

        return $this->user()?->can('update', $indirectCostType) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->has('code') ? strtoupper(trim((string) $this->input('code'))) : $this->input('code'),
            'color' => filled($this->input('color')) ? $this->input('color') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IndirectCostType $indirectCostType */
        $indirectCostType = $this->route('indirect_cost_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('indirect_cost_types', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($indirectCostType->id),
            ],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ];
    }
}
