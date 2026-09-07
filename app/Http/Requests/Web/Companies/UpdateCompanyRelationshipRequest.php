<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Domain\Companies\Support\CompanyValidation;
use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        return $this->user()?->can('update', $relationship) ?? false;
    }

    protected function prepareForValidation(): void
    {
        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        $this->merge(CompanyValidation::blankToNull($this->all(), [
            'owner_reference', 'related_reference', 'brand_id', 'external_code',
            'notes', 'starts_at', 'ends_at',
        ]));

        $this->merge([
            'owner_company_id' => $relationship->owner_company_id,
            'related_mode' => 'existing',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        return CompanyValidation::relationshipRules($relationship->owner_company_id, $relationship->id);
    }
}
