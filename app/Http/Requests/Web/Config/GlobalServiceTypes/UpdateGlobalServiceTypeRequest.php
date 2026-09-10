<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\GlobalServiceTypes;

use App\Models\GlobalServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGlobalServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GlobalServiceType $globalServiceType */
        $globalServiceType = $this->route('global_service_type');

        return $this->user()?->can('update', $globalServiceType) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => filled($this->input('code')) ? $this->input('code') : null,
            'color' => filled($this->input('color')) ? $this->input('color') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var GlobalServiceType $globalServiceType */
        $globalServiceType = $this->route('global_service_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('global_service_types', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($globalServiceType->id),
            ],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ];
    }
}
