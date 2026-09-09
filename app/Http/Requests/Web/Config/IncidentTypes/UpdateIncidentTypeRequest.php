<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\IncidentTypes;

use App\Models\IncidentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIncidentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IncidentType $incidentType */
        $incidentType = $this->route('incident_type');

        return $this->user()?->can('update', $incidentType) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'color' => filled($this->input('color')) ? $this->input('color') : null,
            'default_priority_id' => filled($this->input('default_priority_id'))
                ? $this->integer('default_priority_id')
                : null,
            'origin_selectable' => $this->boolean('origin_selectable'),
            'origin_required' => $this->boolean('origin_required'),
            'show_related' => $this->boolean('show_related'),
            'origin_options' => is_array($this->input('origin_options')) ? $this->input('origin_options') : [],
            'default_origin_type' => filled($this->input('default_origin_type'))
                ? $this->string('default_origin_type')->toString()
                : null,
            'related_type' => filled($this->input('related_type'))
                ? $this->string('related_type')->toString()
                : null,
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
            'default_priority_id' => [
                'nullable',
                'integer',
                Rule::exists('incident_priorities', 'id')->whereNull('deleted_at'),
            ],
            'origin_selectable' => ['required', 'boolean'],
            'origin_options' => ['nullable', 'array'],
            'origin_options.*' => ['string', Rule::in(['company', 'brand', 'establishment'])],
            'default_origin_type' => ['nullable', 'string', Rule::in(['company', 'brand', 'establishment'])],
            'origin_required' => ['required', 'boolean'],
            'related_type' => ['nullable', 'string', Rule::in(['evaluation'])],
            'show_related' => ['required', 'boolean'],
        ];
    }
}
