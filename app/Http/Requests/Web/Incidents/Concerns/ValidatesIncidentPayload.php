<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Incidents\Concerns;

use App\Domain\Companies\Support\ActiveCompany;
use App\Domain\Incidents\Services\IncidentService;
use App\Models\Establishment;
use App\Models\IncidentType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesIncidentPayload
{
    protected function prepareIncidentPayload(): void
    {
        $this->merge([
            'subject' => filled($this->input('subject')) ? $this->input('subject') : null,
            'comment' => filled($this->input('comment')) ? $this->input('comment') : null,
            'incident_status_id' => filled($this->input('incident_status_id')) ? $this->integer('incident_status_id') : null,
            'incident_priority_id' => filled($this->input('incident_priority_id')) ? $this->integer('incident_priority_id') : null,
            'incident_type_id' => filled($this->input('incident_type_id')) ? $this->integer('incident_type_id') : null,
            'incident_subtype_id' => filled($this->input('incident_subtype_id')) ? $this->integer('incident_subtype_id') : null,
            'requester_user_id' => filled($this->input('requester_user_id')) ? $this->integer('requester_user_id') : null,
            'responsible_user_id' => filled($this->input('responsible_user_id')) ? $this->integer('responsible_user_id') : null,
            'qc_responsible_user_id' => filled($this->input('qc_responsible_user_id')) ? $this->integer('qc_responsible_user_id') : null,
            'control_at' => filled($this->input('control_at')) ? $this->input('control_at') : null,
            'origin_type' => filled($this->input('origin_type')) ? $this->string('origin_type')->toString() : null,
            'origin_id' => filled($this->input('origin_id')) ? $this->integer('origin_id') : null,
            'related_type' => filled($this->input('related_type')) ? $this->string('related_type')->toString() : null,
            'related_id' => filled($this->input('related_id')) ? $this->integer('related_id') : null,
            'collaborator_ids' => array_values(array_filter(
                (array) $this->input('collaborator_ids', []),
                fn (mixed $id): bool => $id !== '' && $id !== null,
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function incidentRules(bool $statusMustBeOpen): array
    {
        $owner = app(ActiveCompany::class)->forUser($this->user());
        abort_if($owner === null, 403);

        $service = app(IncidentService::class);
        $accessible = $service->accessibleCompanyIds($owner);
        $companyIds = $accessible === [] ? [0] : $accessible;
        $brandIds = $service->accessibleBrandIds($accessible);
        $brandIds = $brandIds === [] ? [0] : $brandIds;
        $establishmentIds = $accessible === []
            ? [0]
            : Establishment::query()
                ->whereIn('company_id', $accessible)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        $establishmentIds = $establishmentIds === [] ? [0] : $establishmentIds;

        $statusRule = Rule::exists('incident_statuses', 'id')->whereNull('deleted_at');
        if ($statusMustBeOpen) {
            $statusRule = $statusRule->where('is_open', true);
        }

        $subtypeRule = Rule::exists('incident_subtypes', 'id')->whereNull('deleted_at');
        $typeId = $this->integer('incident_type_id');
        if ($typeId > 0) {
            $subtypeRule = $subtypeRule->where('incident_type_id', $typeId);
        }

        $originType = $this->input('origin_type');
        $originIdRules = ['nullable', 'integer'];
        if ($originType === 'establishment') {
            $originIdRules[] = Rule::exists('establishments', 'id')->whereNull('deleted_at')->whereIn('company_id', $companyIds);
        } elseif ($originType === 'company') {
            $originIdRules[] = Rule::in($companyIds);
        } elseif ($originType === 'brand') {
            $originIdRules[] = Rule::in($brandIds);
        }

        $relatedIdRules = ['nullable', 'integer'];
        if ($this->input('related_type') === 'evaluation') {
            $relatedIdRules[] = Rule::exists('evaluations', 'id')
                ->whereNull('deleted_at')
                ->whereIn('establishment_id', $establishmentIds);
        }

        return [
            'subject' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
            'incident_type_id' => ['required', 'integer', Rule::exists('incident_types', 'id')->whereNull('deleted_at')],
            'incident_subtype_id' => ['required', 'integer', $subtypeRule],
            'incident_priority_id' => ['required', 'integer', Rule::exists('incident_priorities', 'id')->whereNull('deleted_at')],
            'incident_status_id' => ['nullable', 'integer', $statusRule],
            'responsible_user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'qc_responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'requester_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'control_at' => ['nullable', 'date'],
            'origin_type' => ['nullable', 'string', Rule::in(['establishment', 'company', 'brand'])],
            'origin_id' => $originIdRules,
            'related_type' => ['nullable', 'string', Rule::in(['evaluation'])],
            'related_id' => $relatedIdRules,
        ];
    }

    protected function afterIncidentValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $typeId = $this->integer('incident_type_id');
            if ($typeId <= 0) {
                return;
            }

            $type = IncidentType::query()->find($typeId);
            if ($type === null) {
                return;
            }

            $workflow = $type->formConfig();

            $originType = $this->input('origin_type');
            $originId = $this->input('origin_id');

            if ($workflow['origin_required'] && ($originType === null || $originId === null)) {
                $validator->errors()->add('origin_id', __('validation.required', ['attribute' => 'origin']));
            }

            if ($originType !== null && $workflow['origin_options'] !== [] && ! in_array($originType, $workflow['origin_options'], true)) {
                $validator->errors()->add('origin_type', __('validation.in', ['attribute' => 'origin_type']));
            }

            if ($workflow['show_related'] && $workflow['related_type'] !== null) {
                $relatedType = $this->input('related_type');
                if ($relatedType !== null && $relatedType !== $workflow['related_type']) {
                    $validator->errors()->add('related_type', __('validation.in', ['attribute' => 'related_type']));
                }
            }
        });
    }
}
