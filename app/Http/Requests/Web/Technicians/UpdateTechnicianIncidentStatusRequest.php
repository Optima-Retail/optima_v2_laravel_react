<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Technicians;

use App\Models\TechnicianIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTechnicianIncidentStatusRequest extends FormRequest
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
            'status_id' => [
                'required',
                'integer',
                Rule::exists('technician_incident_statuses', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
