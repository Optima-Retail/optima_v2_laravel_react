<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\IncidentSubtypes;

use App\Models\IncidentSubtype;
use App\Models\IncidentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SyncIncidentTypeSubtypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('create', IncidentSubtype::class)
            || $user->can('update', IncidentSubtype::class)
            || $user->can('delete', IncidentSubtype::class);
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('subtypes');

        if (! is_array($rows)) {
            return;
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $id = $row['id'] ?? null;

            $normalized[] = [
                'id' => $id === null || $id === '' ? null : (int) $id,
                'name' => $name,
            ];
        }

        $this->merge(['subtypes' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IncidentType $incidentType */
        $incidentType = $this->route('incident_type');

        return [
            'subtypes' => ['required', 'array'],
            'subtypes.*.id' => [
                'nullable',
                'integer',
                Rule::exists('incident_subtypes', 'id')
                    ->where(fn ($query) => $query
                        ->where('incident_type_id', $incidentType->id)
                        ->whereNull('deleted_at')),
            ],
            'subtypes.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var IncidentType $incidentType */
            $incidentType = $this->route('incident_type');
            $rows = $this->input('subtypes', []);

            if (! is_array($rows)) {
                return;
            }

            $names = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $nameKey = mb_strtolower(trim((string) ($row['name'] ?? '')));
                if ($nameKey === '') {
                    continue;
                }

                if (isset($names[$nameKey])) {
                    $validator->errors()->add("subtypes.{$index}.name", __('validation.unique', ['attribute' => 'name']));

                    continue;
                }

                $names[$nameKey] = true;

                $nameTaken = IncidentSubtype::query()
                    ->where('incident_type_id', $incidentType->id)
                    ->whereRaw('LOWER(name) = ?', [$nameKey])
                    ->when($id > 0, fn ($query) => $query->whereKeyNot($id))
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add("subtypes.{$index}.name", __('validation.unique', ['attribute' => 'name']));
                }
            }
        });
    }
}
