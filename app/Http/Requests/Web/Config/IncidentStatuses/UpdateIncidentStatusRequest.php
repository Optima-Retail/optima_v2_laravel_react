<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\IncidentStatuses;

use App\Models\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIncidentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IncidentStatus $incidentStatus */
        $incidentStatus = $this->route('incident_status');

        return $this->user()?->can('update', $incidentStatus) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $excluded = $this->input('excluded_type_ids', []);

        $this->merge([
            'color' => filled($this->input('color')) ? $this->input('color') : null,
            'lifecycle' => filled($this->input('lifecycle')) ? $this->input('lifecycle') : null,
            'is_open' => $this->boolean('is_open'),
            'excluded_type_ids' => is_array($excluded)
                ? array_values(array_unique(array_map('intval', $excluded)))
                : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'lifecycle' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_open' => ['required', 'boolean'],
            'excluded_type_ids' => ['nullable', 'array'],
            'excluded_type_ids.*' => [
                'integer',
                Rule::exists('incident_types', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
