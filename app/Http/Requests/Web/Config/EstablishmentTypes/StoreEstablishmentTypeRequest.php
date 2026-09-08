<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\EstablishmentTypes;

use App\Models\EstablishmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEstablishmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EstablishmentType::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtolower(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('establishment_types', 'code')->whereNull('deleted_at'),
            ],
            'health_and_safety_delay_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }
}
