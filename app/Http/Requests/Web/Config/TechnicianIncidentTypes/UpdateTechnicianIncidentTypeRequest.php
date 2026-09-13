<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\TechnicianIncidentTypes;

use App\Models\TechnicianIncidentType;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTechnicianIncidentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TechnicianIncidentType $technicianIncidentType */
        $technicianIncidentType = $this->route('technician_incident_type');

        return $this->user()?->can('update', $technicianIncidentType) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_mail_to_technician' => $this->boolean('send_mail_to_technician'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'due_days' => ['required', 'integer', 'min:0'],
            'send_mail_to_technician' => ['required', 'boolean'],
        ];
    }
}
