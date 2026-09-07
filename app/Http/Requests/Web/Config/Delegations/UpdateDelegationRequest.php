<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Delegations;

use App\Models\Delegation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Delegation $delegation */
        $delegation = $this->route('delegation');

        return $this->user()?->can('update', $delegation) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax_id' => $this->filled('tax_id') ? trim((string) $this->input('tax_id')) : null,
            'company_id' => $this->filled('company_id') ? $this->integer('company_id') : null,
            'currency_id' => $this->filled('currency_id') ? $this->integer('currency_id') : null,
            'country_id' => $this->filled('country_id') ? $this->integer('country_id') : null,
            'series_id' => $this->filled('series_id') ? $this->integer('series_id') : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'cost_includes_vat' => $this->boolean('cost_includes_vat'),
            'recovers_vat' => $this->boolean('recovers_vat', true),
            'billing_info' => $this->normalizeBillingInfo($this->input('billing_info')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'address' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'series_id' => ['nullable', 'integer', Rule::exists('series', 'id')->whereNull('deleted_at')],
            'cost_includes_vat' => ['required', 'boolean'],
            'recovers_vat' => ['required', 'boolean'],
            'billing_info' => ['nullable', 'array'],
        ];
    }

    private function normalizeBillingInfo(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
