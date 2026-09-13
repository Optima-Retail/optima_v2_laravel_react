<?php

declare(strict_types=1);

namespace App\Domain\Incidents\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Incidents\Support\IncidentLineStatusRules;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\Evaluation;
use App\Models\Incident;
use App\Models\IncidentPriority;
use App\Models\IncidentStatus;
use App\Models\IncidentSubtype;
use App\Models\IncidentType;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class IncidentService
{
    public function __construct(
        private readonly IncidentLineStatusRules $lineStatusRules,
    ) {}

    /**
     * Client company IDs linked to the owner (kind = customer).
     *
     * @return list<int>
     */
    public function accessibleCompanyIds(Company $owner): array
    {
        return $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('related_company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Establishments for accessible client companies.
     *
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function establishmentOptions(Company $owner): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        return Establishment::query()
            ->whereIn('company_id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'company_id'])
            ->map(fn (Establishment $establishment): array => [
                'id' => $establishment->id,
                'label' => $establishment->code
                    ? "{$establishment->name} ({$establishment->code})"
                    : $establishment->name,
                'company_id' => (int) $establishment->company_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Evaluations for accessible client company establishments.
     *
     * @return list<array{id: int, label: string}>
     */
    public function evaluationOptions(Company $owner): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        return Evaluation::query()
            ->whereHas('establishment', function ($query) use ($ids): void {
                $query->whereIn('company_id', $ids);
            })
            ->orderByDesc('id')
            ->get(['id', 'subject', 'public_id'])
            ->map(fn (Evaluation $evaluation): array => [
                'id' => $evaluation->id,
                'label' => $evaluation->subject
                    ? "#{$evaluation->id} — {$evaluation->subject}"
                    : "#{$evaluation->id} — {$evaluation->public_id}",
            ])
            ->values()
            ->all();
    }

    /**
     * Active (`is_open`) statuses for create / header display.
     * Optionally keep a current inactive status so edit still shows the saved value.
     *
     * @return list<array{id: int, label: string, color: string|null, is_open: bool}>
     */
    public function incidentStatusOptions(?int $includeId = null): array
    {
        return IncidentStatus::query()
            ->where(function ($query) use ($includeId): void {
                $query->where('is_open', true);

                if ($includeId !== null) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('lifecycle')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'is_open'])
            ->map(fn (IncidentStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'is_open' => (bool) $status->is_open,
            ])
            ->values()
            ->all();
    }

    /**
     * Statuses available when adding an Acciones line: open statuses only (`is_open`).
     * Type exclusions come from `incident_status_type_exclusions`.
     *
     * @return list<array{id: int, label: string, color: string|null, is_open: bool}>
     */
    public function selectableIncidentStatusOptions(?Incident $incident = null): array
    {
        $options = IncidentStatus::query()
            ->where('is_open', true)
            ->orderBy('lifecycle')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'is_open'])
            ->map(fn (IncidentStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'is_open' => (bool) $status->is_open,
            ])
            ->values()
            ->all();

        return $this->lineStatusRules->filterStatusOptions(
            $incident?->incident_type_id !== null ? (int) $incident->incident_type_id : null,
            $options,
        );
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function incidentPriorityOptions(): array
    {
        return IncidentPriority::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (IncidentPriority $priority): array => [
                'id' => $priority->id,
                'label' => $priority->name,
                'color' => $priority->color,
            ])
            ->values()
            ->all();
    }

    /**
     * Client companies linked to the owner (kind = customer).
     *
     * @return list<array{id: int, label: string}>
     */
    public function clientOptions(Company $owner): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'tradename'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'label' => $company->tradename
                    ? "{$company->name} ({$company->tradename})"
                    : $company->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Brands used by accessible client companies.
     *
     * @return list<array{id: int, label: string}>
     */
    public function brandOptions(Company $owner): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        $brandIds = Company::query()
            ->whereIn('id', $ids)
            ->whereNotNull('brand_id')
            ->pluck('brand_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($brandIds === []) {
            return [];
        }

        return Brand::query()
            ->whereIn('id', $brandIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'label' => $brand->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Form behaviour keyed by incident type id (from `incident_types` rows).
     *
     * @return array<string, array{
     *     origin_selectable: bool,
     *     origin_options: list<string>,
     *     default_origin_type: string|null,
     *     origin_required: bool,
     *     related_type: string|null,
     *     show_related: bool
     * }>
     */
    public function typeWorkflow(): array
    {
        $map = [];

        foreach (IncidentType::query()->orderBy('id')->get() as $type) {
            $map[(string) $type->id] = $type->formConfig();
        }

        return $map;
    }

    /**
     * @return list<array{id: int, label: string, color: string|null, default_priority_id: int|null}>
     */
    public function incidentTypeOptions(): array
    {
        return IncidentType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'default_priority_id'])
            ->map(fn (IncidentType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
                'color' => $type->color,
                'default_priority_id' => $type->default_priority_id !== null
                    ? (int) $type->default_priority_id
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, incident_type_id: int}>
     */
    public function incidentSubtypeOptions(): array
    {
        return IncidentSubtype::query()
            ->orderBy('name')
            ->get(['id', 'name', 'incident_type_id'])
            ->map(fn (IncidentSubtype $subtype): array => [
                'id' => $subtype->id,
                'label' => $subtype->name,
                'incident_type_id' => (int) $subtype->incident_type_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(Company $owner, array $includeUserIds = []): array
    {
        return CompanyMemberUsers::options($owner, $includeUserIds);
    }

    /**
     * Default status: legacy "Abierta - QC" (id 89) when present, otherwise first open status.
     */
    public function defaultIncidentStatusId(): ?int
    {
        $abierta = IncidentStatus::query()
            ->whereKey(89)
            ->where('is_open', true)
            ->value('id');

        if ($abierta !== null) {
            return (int) $abierta;
        }

        $open = IncidentStatus::query()
            ->where('is_open', true)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $open !== null ? (int) $open : null;
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, incident_status_id?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Incident>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $incidentStatusId = trim((string) ($filters['incident_status_id'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'subject', 'control_at', 'closed_at', 'created_at'],
            'id',
        );

        return Incident::query()
            ->with(['establishment', 'status', 'priority', 'type', 'responsibleUser'])
            ->where('company_id', $owner->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('subject', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('establishment', function ($establishmentQuery) use ($search): void {
                            $establishmentQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($incidentStatusId !== '', fn ($query) => $query->where('incident_status_id', (int) $incidentStatusId))
            ->when($createdFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<int>  $companyIds
     * @return list<int>
     */
    public function accessibleBrandIds(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $companyIds)
            ->whereNotNull('brand_id')
            ->pluck('brand_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, incident_status_id?: string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (Incident $incident): array => $this->toListItem($incident));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $owner, array $data): Incident
    {
        return DB::transaction(function () use ($owner, $data): Incident {
            $attributes = $this->attributes($data);
            $attributes['company_id'] = $owner->id;

            if (($attributes['incident_status_id'] ?? null) === null) {
                $attributes['incident_status_id'] = $this->defaultIncidentStatusId();
            }

            $incident = Incident::query()->create($attributes);
            $incident->collaborators()->sync($data['collaborator_ids'] ?? []);

            return $incident->load([
                'establishment',
                'status',
                'priority',
                'type',
                'subtype',
                'evaluation',
                'requesterUser',
                'responsibleUser',
                'qcResponsibleUser',
                'collaborators',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, array $data): Incident
    {
        return DB::transaction(function () use ($incident, $data): Incident {
            // Match prod show: tipo / estado / origen_modelo / relacion_modelo are not changed via header save.
            $attributes = $this->attributes($data);
            $attributes['incident_type_id'] = $incident->incident_type_id;
            $attributes['incident_status_id'] = $incident->incident_status_id;
            $attributes['origin_type'] = $incident->origin_type;
            $attributes['related_type'] = $incident->related_type;
            $attributes['closed_at'] = $incident->closed_at;
            $attributes['duration_seconds'] = $incident->duration_seconds;
            $attributes['qc_duration_seconds'] = $incident->qc_duration_seconds;

            $incident->update($attributes);
            $incident->collaborators()->sync($data['collaborator_ids'] ?? []);

            return $incident->fresh([
                'establishment',
                'status',
                'priority',
                'type',
                'subtype',
                'evaluation',
                'requesterUser',
                'responsibleUser',
                'qcResponsibleUser',
                'collaborators',
            ]) ?? $incident;
        });
    }

    public function delete(Incident $incident): void
    {
        if ($incident->trashed()) {
            return;
        }

        DB::transaction(function () use ($incident): void {
            $incident->collaborators()->detach();
            $incident->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(Incident $incident): array
    {
        $incident->loadMissing('collaborators');

        return [
            'id' => $incident->id,
            'company_id' => $incident->company_id,
            'subject' => $incident->subject,
            'comment' => $incident->comment,
            'collaborator_ids' => $incident->collaborators->pluck('id')->values()->all(),
            'establishment_id' => $incident->establishment_id,
            'evaluation_id' => $incident->evaluation_id,
            'incident_status_id' => $incident->incident_status_id,
            'incident_priority_id' => $incident->incident_priority_id,
            'incident_type_id' => $incident->incident_type_id,
            'incident_subtype_id' => $incident->incident_subtype_id,
            'requester_user_id' => $incident->requester_user_id,
            'responsible_user_id' => $incident->responsible_user_id,
            'qc_responsible_user_id' => $incident->qc_responsible_user_id,
            'control_at' => $incident->control_at?->format('Y-m-d\TH:i'),
            'duration_seconds' => $incident->duration_seconds,
            'qc_duration_seconds' => $incident->qc_duration_seconds,
            'closed_at' => $incident->closed_at?->format('Y-m-d\TH:i'),
            'origin_type' => $incident->origin_type,
            'origin_id' => $incident->origin_id,
            'related_type' => $incident->related_type,
            'related_id' => $incident->related_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'subject' => $incident->subject,
            'establishment_name' => $incident->establishment?->name,
            'status_name' => $incident->status?->name,
            'status_color' => $incident->status?->color,
            'priority_name' => $incident->priority?->name,
            'priority_color' => $incident->priority?->color,
            'type_name' => $incident->type?->name,
            'type_color' => $incident->type?->color,
            'responsible_user_name' => $incident->responsibleUser?->name,
            'control_at' => $incident->control_at?->toIso8601String(),
            'closed_at' => $incident->closed_at?->toIso8601String(),
            'created_at' => $incident->created_at?->toIso8601String(),
        ];
    }

    public function canAccess(Company $owner, Incident $incident): bool
    {
        return (int) $incident->company_id === (int) $owner->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $originType = filled($data['origin_type'] ?? null) ? (string) $data['origin_type'] : null;
        $originId = isset($data['origin_id']) && $data['origin_id'] !== '' && $data['origin_id'] !== null
            ? (int) $data['origin_id']
            : null;
        $relatedType = filled($data['related_type'] ?? null) ? (string) $data['related_type'] : null;
        $relatedId = isset($data['related_id']) && $data['related_id'] !== '' && $data['related_id'] !== null
            ? (int) $data['related_id']
            : null;

        if ($originType === null || $originId === null) {
            $originType = null;
            $originId = null;
        }

        if ($relatedType === null || $relatedId === null) {
            $relatedType = null;
            $relatedId = null;
        }

        $establishmentId = $originType === 'establishment' ? $originId : null;
        $evaluationId = $relatedType === 'evaluation' ? $relatedId : null;

        return Arr::except([
            'subject' => $data['subject'] ?? null,
            'comment' => $data['comment'] ?? null,
            'establishment_id' => $establishmentId,
            'evaluation_id' => $evaluationId,
            'incident_status_id' => $data['incident_status_id'] ?? null,
            'incident_priority_id' => $data['incident_priority_id'] ?? null,
            'incident_type_id' => $data['incident_type_id'] ?? null,
            'incident_subtype_id' => $data['incident_subtype_id'] ?? null,
            'requester_user_id' => $data['requester_user_id'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'qc_responsible_user_id' => $data['qc_responsible_user_id'] ?? null,
            'control_at' => $data['control_at'] ?? null,
            'origin_type' => $originType,
            'origin_id' => $originId,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ], ['collaborator_ids']);
    }
}
