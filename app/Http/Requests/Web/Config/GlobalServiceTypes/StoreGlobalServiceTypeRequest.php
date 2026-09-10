<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\GlobalServiceTypes;

use App\Models\GlobalServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreGlobalServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GlobalServiceType::class) ?? false;
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('global_service_types', 'code')->whereNull('deleted_at'),
            ],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ];
    }
}
