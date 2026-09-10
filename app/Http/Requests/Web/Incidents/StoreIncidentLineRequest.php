<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Incidents;

use App\Domain\Incidents\Services\IncidentService;
use App\Domain\Incidents\Support\IncidentLineStatusRules;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreIncidentLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Incident $incident */
        $incident = $this->route('incident');

        return $this->user()?->can('update', $incident) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comment' => is_string($this->input('comment'))
                ? trim($this->input('comment'))
                : $this->input('comment'),
            'incident_status_id' => filled($this->input('incident_status_id'))
                ? $this->integer('incident_status_id')
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Incident $incident */
        $incident = $this->route('incident');

        $allowedIds = array_map(
            static fn (array $option): int => (int) $option['id'],
            app(IncidentService::class)->selectableIncidentStatusOptions($incident),
        );

        $forbidden = app(IncidentLineStatusRules::class)->forbiddenStatusIdsForType(
            $incident->incident_type_id !== null ? (int) $incident->incident_type_id : null,
        );

        $rules = [
            'comment' => ['required', 'string', 'max:5000'],
            'incident_status_id' => [
                'required',
                'integer',
                Rule::exists('incident_statuses', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_open', true),
                Rule::in($allowedIds !== [] ? $allowedIds : [0]),
            ],
        ];

        if ($forbidden !== []) {
            $rules['incident_status_id'][] = Rule::notIn($forbidden);
        }

        return $rules;
    }
}
