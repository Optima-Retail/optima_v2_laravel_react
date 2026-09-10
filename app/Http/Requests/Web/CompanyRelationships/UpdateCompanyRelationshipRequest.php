<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

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

        $this->merge(CompanyValidation::blankToNull($this->all(), CompanyValidation::relationshipNullableKeys()));

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
            'owner_company_id' => $relationship->owner_company_id,
            'related_mode' => 'existing',
            'collaborator_ids' => array_values(array_filter(
                (array) $this->input('collaborator_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
            'priority_ids' => array_values(array_filter(
                (array) $this->input('priority_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
            'service_type_ids' => array_values(array_filter(
                (array) $this->input('service_type_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
            'global_service_type_ids' => array_values(array_filter(
                (array) $this->input('global_service_type_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
            'alternative_delegation_ids' => array_values(array_filter(
                (array) $this->input('alternative_delegation_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
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
