<?php

declare(strict_types=1);

namespace App\Domain\Technicians\Incidents\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\StatusChanges\Services\StatusChangeHistoryService;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\TechnicianIncident;
use App\Models\TechnicianIncidentStatus;
use App\Models\TechnicianIncidentType;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class TechnicianIncidentService
{
    /** Legacy TecnicoIncidenciaTipoEnum::NEGOCIACION */
    public const NEGOTIATION_TYPE_ID = 2;

    public function __construct(
        private readonly StatusChangeHistoryService $statusChanges,
    ) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null,
     *     technician_id?: int|string|null,
     *     status_id?: string|null,
     *     created_from?: string|null,
     *     created_to?: string|null
     * }  $filters
     * @return LengthAwarePaginator<int, TechnicianIncident>
     */
    public function paginate(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $technicianId = isset($filters['technician_id']) && $filters['technician_id'] !== '' && $filters['technician_id'] !== null
            ? (int) $filters['technician_id']
            : null;
        $statusId = trim((string) ($filters['status_id'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'due_at', 'responded_at', 'verified_at', 'created_at', 'is_verified'],
            'id',
        );

        return TechnicianIncident::query()
            ->with(['status', 'type', 'technician.relatedCompany', 'requestedBy', 'respondedBy', 'verifiedBy'])
            ->whereHas('technician', function ($query) use ($owner): void {
                $query
                    ->where('owner_company_id', $owner->id)
                    ->where('kind', CompanyRelationshipKind::Technician->value);
            })
            ->when($technicianId !== null, function ($query) use ($technicianId): void {
                $query->where('technician_id', $technicianId);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('incident_text', 'like', "%{$search}%");
            })
            ->when($statusId !== '', fn ($query) => $query->where('status_id', (int) $statusId))
            ->when($createdFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null,
     *     technician_id?: int|string|null,
     *     status_id?: string|null,
     *     created_from?: string|null,
     *     created_to?: string|null
     * }  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($owner, $filters, $perPage)
            ->through(fn (TechnicianIncident $incident): array => $this->toListItem($incident));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): TechnicianIncident
    {
        return DB::transaction(function () use ($data, $actor): TechnicianIncident {
            if (! array_key_exists('requested_by_id', $data) || $data['requested_by_id'] === null) {
                $data['requested_by_id'] = $actor?->id;
            }

            if (! array_key_exists('status_id', $data) || $data['status_id'] === null || $data['status_id'] === '') {
                $data['status_id'] = $this->defaultOpenStatusId();
            }

            if (! array_key_exists('is_verified', $data) || $data['is_verified'] === null) {
                $data['is_verified'] = false;
            }

            if (empty($data['due_at'])) {
                $typeId = (int) ($data['technician_incident_type_id'] ?? 0);
                $dueDays = TechnicianIncidentType::query()->whereKey($typeId)->value('due_days');

                if ($dueDays !== null) {
                    $data['due_at'] = now()->addDays((int) $dueDays);
                }
            }

            $incident = TechnicianIncident::query()->create($this->attributes($data));

            $this->statusChanges->recordTechnicianIncident(
                (int) $incident->id,
                null,
                $incident->status_id !== null ? (int) $incident->status_id : null,
                $actor,
            );

            return $incident->load(['status', 'type', 'technician', 'requestedBy', 'respondedBy', 'verifiedBy']);
        });
    }

    /**
     * Default status from Config → Technician incident statuses (`is_default`),
     * otherwise the earliest open status by lifecycle.
     */
    public function defaultOpenStatusId(): ?int
    {
        $default = TechnicianIncidentStatus::query()
            ->default()
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        if ($default !== null) {
            return (int) $default;
        }

        $fallback = TechnicianIncidentStatus::query()
            ->where('is_open', true)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    public function verifiedStatusId(): ?int
    {
        $id = TechnicianIncidentStatus::query()
            ->marksVerified()
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function statusSetsResponseDate(?int $statusId): bool
    {
        if ($statusId === null) {
            return false;
        }

        return TechnicianIncidentStatus::query()
            ->whereKey($statusId)
            ->setsResponseDate()
            ->exists();
    }

    public function statusMarksVerified(?int $statusId): bool
    {
        if ($statusId === null) {
            return false;
        }

        return TechnicianIncidentStatus::query()
            ->whereKey($statusId)
            ->marksVerified()
            ->exists();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function typeOptions(): array
    {
        return TechnicianIncidentType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TechnicianIncidentType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
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
     * @return list<array{id: int, label: string}>
     */
    public function technicianOptions(Company $owner): array
    {
        return CompanyRelationship::query()
            ->with('relatedCompany:id,name,logo')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->orderBy('id')
            ->get()
            ->map(fn (CompanyRelationship $relationship): array => $relationship->toSelectOption(
                $relationship->relatedCompany?->name ?? (string) $relationship->id,
            ))
            ->sortBy(fn (array $option): string => mb_strtolower($option['label']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TechnicianIncident $incident, array $data): TechnicianIncident
    {
        return DB::transaction(function () use ($incident, $data): TechnicianIncident {
            $oldStatusId = $incident->status_id !== null ? (int) $incident->status_id : null;

            $incident->update($this->attributes($data));

            $fresh = $incident->fresh(['status', 'type', 'technician', 'requestedBy', 'respondedBy', 'verifiedBy'])
                ?? $incident;

            $this->statusChanges->recordTechnicianIncident(
                (int) $fresh->id,
                $oldStatusId,
                $fresh->status_id !== null ? (int) $fresh->status_id : null,
            );

            return $fresh;
        });
    }

    public function delete(TechnicianIncident $incident): void
    {
        if ($incident->trashed()) {
            return;
        }

        DB::transaction(function () use ($incident): void {
            $incident->delete();
        });
    }

    /**
     * Active (`is_open`) statuses for technician incident forms.
     * Optionally keep a current closed status so edit still shows the saved value.
     *
     * @return list<array{id: int, label: string, color: string|null, is_open: bool}>
     */
    public function statusOptions(?int $includeId = null): array
    {
        return TechnicianIncidentStatus::query()
            ->withTrashed()
            ->where(function ($query) use ($includeId): void {
                $query->whereNull('deleted_at')->where('is_open', true);

                if ($includeId !== null) {
                    $query->orWhereKey($includeId);
                }
            })
            ->orderByRaw('lifecycle is null')
            ->orderBy('lifecycle')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'is_open'])
            ->map(fn (TechnicianIncidentStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'is_open' => (bool) $status->is_open,
            ])
            ->values()
            ->all();
    }

    public function updateStatus(TechnicianIncident $incident, int $statusId, ?User $actor = null): TechnicianIncident
    {
        return DB::transaction(function () use ($incident, $statusId, $actor): TechnicianIncident {
            $oldStatusId = $incident->status_id !== null ? (int) $incident->status_id : null;
            $payload = ['status_id' => $statusId];
            $leavingVerified = $this->statusMarksVerified($oldStatusId) && ! $this->statusMarksVerified($statusId);
            $enteringVerified = $this->statusMarksVerified($statusId) && ! $this->statusMarksVerified($oldStatusId);

            // Leaving a marks_verified status clears verify metadata so verify can run again.
            if ($leavingVerified) {
                $payload['is_verified'] = false;
                $payload['verified_by_id'] = null;
                $payload['verified_at'] = null;
            }

            // Statuses with sets_response_date stamp responded_at when entered.
            if (
                $incident->responded_at === null
                && $oldStatusId !== $statusId
                && $this->statusSetsResponseDate($statusId)
            ) {
                $payload['responded_at'] = now()->toDateString();
            }

            // Entering a marks_verified status stamps verifier when not already set.
            if (
                $enteringVerified
                && $incident->verified_by_id === null
                && $incident->verified_at === null
            ) {
                $verifier = $actor ?? (Auth::user() instanceof User ? Auth::user() : null);
                $payload['is_verified'] = true;
                $payload['verified_by_id'] = $verifier?->id;
                $payload['verified_at'] = now();
            }

            $incident->update($this->attributes($payload));

            $fresh = $incident->fresh(['status', 'type', 'technician.relatedCompany', 'requestedBy', 'respondedBy', 'verifiedBy'])
                ?? $incident;

            $this->statusChanges->recordTechnicianIncident(
                (int) $fresh->id,
                $oldStatusId,
                (int) $statusId,
                $actor,
            );

            return $fresh;
        });
    }

    /**
     * @param  array{
     *     response_text?: string|null,
     *     negotiation_succeeded?: bool|null,
     *     unsuccessful_negotiation_solution?: string|null
     * }  $data
     */
    public function verify(TechnicianIncident $incident, User $actor, array $data = []): TechnicianIncident
    {
        return DB::transaction(function () use ($incident, $actor, $data): TechnicianIncident {
            if ($incident->is_verified) {
                return $incident->load(['status', 'type', 'technician.relatedCompany', 'requestedBy', 'respondedBy', 'verifiedBy']);
            }

            $typeId = (int) $incident->technician_incident_type_id;
            $isNegotiation = $typeId === self::NEGOTIATION_TYPE_ID;
            $oldStatusId = $incident->status_id !== null ? (int) $incident->status_id : null;

            $payload = [
                'is_verified' => true,
                'verified_by_id' => $actor->id,
                'verified_at' => now(),
            ];

            // Prod closes with fecha_respuesta before verify; v2 verify is the resolve action, so stamp it here.
            if ($incident->responded_at === null) {
                $payload['responded_at'] = now()->toDateString();
            }

            if (array_key_exists('response_text', $data) && $data['response_text'] !== null) {
                $payload['response_text'] = $data['response_text'];
            }

            if ($isNegotiation) {
                $succeeded = array_key_exists('negotiation_succeeded', $data)
                    ? (bool) $data['negotiation_succeeded']
                    : null;

                $payload['negotiation_succeeded'] = $succeeded;

                if ($succeeded === false) {
                    $solution = trim((string) ($data['unsuccessful_negotiation_solution'] ?? ''));
                    $payload['unsuccessful_negotiation_solution'] = $solution !== ''
                        ? $solution
                        : 'No solution provided';
                } else {
                    $payload['unsuccessful_negotiation_solution'] = null;
                }
            }

            $verifiedStatusId = $this->verifiedStatusId();

            if ($verifiedStatusId !== null) {
                $payload['status_id'] = $verifiedStatusId;
            }

            $incident->update($this->attributes($payload));

            $fresh = $incident->fresh(['status', 'type', 'technician.relatedCompany', 'requestedBy', 'respondedBy', 'verifiedBy'])
                ?? $incident;

            $this->statusChanges->recordTechnicianIncident(
                (int) $fresh->id,
                $oldStatusId,
                $fresh->status_id !== null ? (int) $fresh->status_id : null,
                $actor,
            );

            return $fresh;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(TechnicianIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'status_id' => $incident->status_id,
            'technician_incident_type_id' => $incident->technician_incident_type_id,
            'incident_text' => $incident->incident_text,
            'response_text' => $incident->response_text,
            'requested_by_id' => $incident->requested_by_id,
            'responded_by_id' => $incident->responded_by_id,
            'responded_at' => $incident->responded_at?->format('Y-m-d'),
            'technician_id' => $incident->technician_id,
            'is_verified' => $incident->is_verified,
            'verified_at' => $incident->verified_at?->toIso8601String(),
            'verified_by_id' => $incident->verified_by_id,
            'due_at' => $incident->due_at?->toIso8601String(),
            'negotiation_succeeded' => $incident->negotiation_succeeded,
            'unsuccessful_negotiation_solution' => $incident->unsuccessful_negotiation_solution,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailData(TechnicianIncident $incident): array
    {
        $incident->loadMissing(['status', 'type', 'technician.relatedCompany', 'requestedBy', 'respondedBy', 'verifiedBy']);

        return $this->toFormData($incident) + [
            'status_name' => $incident->status?->name,
            'status_color' => $incident->status?->color,
            'type_name' => $incident->type?->name,
            'technician_name' => $incident->technician?->relatedCompany?->name,
            'requested_by_name' => $incident->requestedBy?->name,
            'responded_by_name' => $incident->respondedBy?->name,
            'verified_by_name' => $incident->verifiedBy?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(TechnicianIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'status_id' => $incident->status_id,
            'status_name' => $incident->status?->name,
            'status_color' => $incident->status?->color,
            'technician_incident_type_id' => $incident->technician_incident_type_id,
            'type_name' => $incident->type?->name,
            'incident_text' => $incident->incident_text,
            'technician_id' => $incident->technician_id,
            'technician_name' => $incident->technician?->relatedCompany?->name,
            'requested_by_id' => $incident->requested_by_id,
            'requested_by_name' => $incident->requestedBy?->name,
            'responded_by_id' => $incident->responded_by_id,
            'responded_by_name' => $incident->respondedBy?->name,
            'is_verified' => $incident->is_verified,
            'due_at' => $incident->due_at?->toIso8601String(),
            'responded_at' => $incident->responded_at?->format('Y-m-d'),
            'created_at' => $incident->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'status_id',
            'technician_incident_type_id',
            'incident_text',
            'response_text',
            'requested_by_id',
            'responded_by_id',
            'responded_at',
            'technician_id',
            'is_verified',
            'verified_at',
            'verified_by_id',
            'due_at',
            'negotiation_succeeded',
            'unsuccessful_negotiation_solution',
        ]);
    }
}
