<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Domain\Companies\Support\CompanyValidation;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Company::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(CompanyValidation::blankToNull($this->all(), [
            'tradename', 'slug', 'tax_id', 'country_id', 'residence_country_id',
            'person_type', 'email', 'phone', 'website', 'address_line_1',
            'address_line_2', 'city', 'province', 'postal_code', 'employee_count',
            'brand_id',
        ]));

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CompanyValidation::companyRules();
    }
}
