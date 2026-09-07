<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Domain\Companies\Services\EstablishmentService;
use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Companies\Support\CompanyValidation;
use App\Models\Establishment;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Establishment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());

        $this->merge(CompanyValidation::blankToNull($this->all(), [
            'code', 'address_line_1', 'address_line_2', 'city', 'province',
            'postal_code', 'country_id', 'timezone_id', 'delegation_id', 'billing_company_id', 'company_id',
        ]));

        $this->merge([
            'company_id' => $this->filled('company_id') ? $this->integer('company_id') : $owner?->id,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());

        abort_if($owner === null, 403);

        $accessible = app(EstablishmentService::class)->accessibleCompanyIds($owner);

        return CompanyValidation::establishmentRules(null, $accessible);
    }
}
