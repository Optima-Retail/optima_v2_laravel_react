<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Companies\Support\CompanyValidation;
use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCompanyRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CompanyRelationship::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());

        $this->merge(CompanyValidation::blankToNull($this->all(), [
            'owner_reference', 'related_reference', 'brand_id', 'external_code',
            'notes', 'starts_at', 'ends_at', 'related_company_id',
        ]));

        $relatedCompany = $this->input('related_company');
        if (is_array($relatedCompany)) {
            $relatedCompany = CompanyValidation::blankToNull($relatedCompany, [
                'tradename', 'tax_id', 'email', 'phone',
            ]);
        }

        $this->merge([
            'owner_company_id' => $owner?->id,
            'related_mode' => $this->input('related_mode', 'existing'),
            'related_company' => $relatedCompany,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());

        abort_if($owner === null, 403);

        return CompanyValidation::relationshipRules($owner->id);
    }
}
