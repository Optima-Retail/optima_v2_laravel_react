<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Technicians;

use App\Models\TechnicianIncident;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTechnicianIncidentMessageRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:10000'],
            'file' => ['nullable', 'file', 'max:20480'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->hasFile('file')) {
                return;
            }

            $plain = trim(strip_tags(str_replace("\n", '', (string) $this->input('body'))));

            if ($plain === '') {
                $validator->errors()->add('body', __('validation.required', ['attribute' => 'body']));
            }
        });
    }
}
