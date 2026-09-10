<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Vehicles;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'brand' => $this->nullableTrimmed('brand'),
            'model' => $this->nullableTrimmed('model'),
            'license_plate' => $this->nullableTrimmed('license_plate'),
            'company_relationship_id' => $this->filled('company_relationship_id')
                ? (int) $this->input('company_relationship_id')
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'license_plate' => ['nullable', 'string', 'max:255'],
            'company_relationship_id' => [
                'required',
                'integer',
                Rule::exists('company_relationships', 'id')
                    ->whereNull('deleted_at')
                    ->where('kind', CompanyRelationshipKind::Technician->value),
            ],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        if (! $this->has($key) || $this->input($key) === null) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
