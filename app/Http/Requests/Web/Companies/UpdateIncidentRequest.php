<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Companies;

use App\Http\Requests\Web\Companies\Concerns\ValidatesIncidentPayload;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $this->prepareIncidentPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Incident $incident */
        $incident = $this->route('incident');
        $currentStatusId = $incident->incident_status_id !== null ? (int) $incident->incident_status_id : null;
        $statusMustBeOpen = ! (
            $currentStatusId !== null
            && $this->integer('incident_status_id') === $currentStatusId
        );

        $rules = $this->incidentRules(statusMustBeOpen: $statusMustBeOpen);

        if (! $statusMustBeOpen) {
            $rules['incident_status_id'] = [
                'nullable',
                'integer',
                Rule::exists('incident_statuses', 'id')->whereNull('deleted_at'),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterIncidentValidation($validator);
    }
}
