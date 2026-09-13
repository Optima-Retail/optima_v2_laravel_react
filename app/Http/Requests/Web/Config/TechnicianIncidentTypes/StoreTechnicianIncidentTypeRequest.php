<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\TechnicianIncidentTypes;

use App\Models\TechnicianIncidentType;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTechnicianIncidentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TechnicianIncidentType::class) ?? false;
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
