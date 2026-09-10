<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Incidents;

use App\Http\Requests\Web\Incidents\Concerns\ValidatesIncidentPayload;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateIncidentRequest extends FormRequest
{
    use ValidatesIncidentPayload;

    public function authorize(): bool
    {
        /** @var Incident $incident */
        $incident = $this->route('incident');

        return $this->user()?->can('update', $incident) ?? false;
    }

    protected function prepareForValidation(): void
    {
        /** @var Incident $incident */
        $incident = $this->route('incident');

        // Match Optima show: type / status / origin_type / related_type are not editable via header save.
        $this->merge([
            'incident_type_id' => $incident->incident_type_id,
            'incident_status_id' => $incident->incident_status_id,
            'origin_type' => $incident->origin_type,
            'related_type' => $incident->related_type,
        ]);

        $this->prepareIncidentPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Status may be closed; header save never changes it (merged above).
        return $this->incidentRules(statusMustBeOpen: false);
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterIncidentValidation($validator);
    }
}
