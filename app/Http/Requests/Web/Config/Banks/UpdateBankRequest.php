<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Banks;

use App\Models\Bank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Bank $bank */
        $bank = $this->route('bank');

        return $this->user()?->can('update', $bank) ?? false;
    }

    protected function prepareForValidation(): void
    {
        /** @var Bank $bank */
        $bank = $this->route('bank');

        $this->merge([
            'country_id' => $this->filled('country_id') ? $this->integer('country_id') : ($bank->country_id ?? 1),
            'legal_name' => filled($this->input('legal_name')) ? $this->input('legal_name') : null,
            'swift_bic' => filled($this->input('swift_bic')) ? strtoupper((string) $this->input('swift_bic')) : null,
            'national_bank_code' => filled($this->input('national_bank_code')) ? $this->input('national_bank_code') : null,
            'lei' => filled($this->input('lei')) ? strtoupper((string) $this->input('lei')) : null,
            'supervisor_code' => filled($this->input('supervisor_code')) ? $this->input('supervisor_code') : null,
            'website' => filled($this->input('website')) ? $this->input('website') : null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Bank $bank */
        $bank = $this->route('bank');

        return [
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country_id' => [
                'required',
                'integer',
                Rule::exists('countries', 'id')->whereNull('deleted_at'),
            ],
            'swift_bic' => ['nullable', 'string', 'max:11'],
            'national_bank_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('banks', 'national_bank_code')
                    ->whereNull('deleted_at')
                    ->where('country_id', $this->integer('country_id'))
                    ->ignore($bank->id),
            ],
            'lei' => ['nullable', 'string', 'max:20'],
            'supervisor_code' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
