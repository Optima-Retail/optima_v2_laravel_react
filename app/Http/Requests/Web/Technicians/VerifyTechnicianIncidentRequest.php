<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Technicians;

use App\Domain\Technicians\Incidents\Services\TechnicianIncidentService;
use App\Models\TechnicianIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class VerifyTechnicianIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TechnicianIncident $incident */
        $incident = $this->route('technician_incident');

        return $this->user()?->can('update', $incident) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'response_text' => ['nullable', 'string', 'max:65535'],
            'negotiation_succeeded' => ['nullable', 'boolean'],
            'unsuccessful_negotiation_solution' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var TechnicianIncident $incident */
            $incident = $this->route('technician_incident');

            if ($incident->is_verified) {
                $validator->errors()->add('incident', 'This incident is already verified.');

                return;
            }

            if ((int) $incident->technician_incident_type_id !== TechnicianIncidentService::NEGOTIATION_TYPE_ID) {
                return;
            }

            if (! $this->exists('negotiation_succeeded') || $this->input('negotiation_succeeded') === null) {
                $validator->errors()->add(
                    'negotiation_succeeded',
                    'Indicate whether the negotiation was successful before verifying.',
                );
            }
        });
    }
}
