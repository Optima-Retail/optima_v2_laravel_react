<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\CompanyRelationships;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\CompanyRelationship;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

final class SyncTechnicianVehiclesRequest extends FormRequest
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

        return $user->can('create', Vehicle::class)
            || $user->can('vehicles.update')
            || $user->can('vehicles.delete');
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('vehicles');

        if (! is_array($rows)) {
            return;
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $brand = $row['brand'] ?? null;
            $model = $row['model'] ?? null;
            $licensePlate = $row['license_plate'] ?? null;

            $normalized[] = [
                'id' => $id === null || $id === '' ? null : (int) $id,
                'brand' => $brand === null || $brand === '' ? null : trim((string) $brand),
                'model' => $model === null || $model === '' ? null : trim((string) $model),
                'license_plate' => $licensePlate === null || $licensePlate === ''
                    ? null
                    : trim((string) $licensePlate),
            ];
        }

        $this->merge(['vehicles' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicles' => ['present', 'array'],
            'vehicles.*.id' => ['nullable', 'integer'],
            'vehicles.*.brand' => ['nullable', 'string', 'max:255'],
            'vehicles.*.model' => ['nullable', 'string', 'max:255'],
            'vehicles.*.license_plate' => ['nullable', 'string', 'max:255'],
        ];
    }
}
