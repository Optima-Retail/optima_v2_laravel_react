<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\CompanyRelationship;
use App\Models\TechnicianServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncTechnicianServiceTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /** @var CompanyRelationship $relationship */
        $relationship = $this->route('relationship');

        if ($relationship->kind !== CompanyRelationshipKind::Technician) {
            abort(404);
        }

        return $user->can('create', TechnicianServiceType::class)
            || $user->can('technician_service_types.update')
            || $user->can('technician_service_types.delete');
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('service_type_ids');

        if (! is_array($ids)) {
            return;
        }

        $normalized = [];

        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }

            $normalized[] = (int) $id;
        }

        $this->merge(['service_type_ids' => array_values(array_unique($normalized))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_type_ids' => ['present', 'array'],
            'service_type_ids.*' => [
                'integer',
                Rule::exists('service_types', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
