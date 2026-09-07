<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Domain\Companies\Support\CompanyValidation;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Company $company */
        $company = $this->route('company');

        return $this->user()?->can('update', $company) ?? false;
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
        /** @var Company $company */
        $company = $this->route('company');

        return CompanyValidation::companyRules($company->id);
    }
}
