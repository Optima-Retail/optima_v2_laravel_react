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

        $this->merge(CompanyValidation::blankToNull($this->all(), CompanyValidation::relationshipNullableKeys()));

        $relatedCompany = $this->input('related_company');
        if (is_array($relatedCompany)) {
            $relatedCompany = CompanyValidation::blankToNull($relatedCompany, [
                'tradename', 'tax_id', 'email', 'phone',
            ]);
        }

        $booleans = [];
        foreach (CompanyValidation::relationshipBooleanKeys() as $key) {
            if ($this->has($key)) {
                $booleans[$key] = $this->boolean($key);
            }
        }

        foreach (['day_start_at', 'day_end_at'] as $timeKey) {
            $value = $this->input($timeKey);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
                $booleans[$timeKey] = $value.':00';
            }
        }

        $this->merge([
            ...$booleans,
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
