<?php

declare(strict_types=1);

namespace App\Domain\TechnicianRequests\Services;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\StatusChanges\Services\StatusChangeHistoryService;
use App\Domain\TechnicianRequests\Enums\TechnicianRequestPriorityKey;
use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusId;
use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Country;
use App\Models\Language;
use App\Models\ServiceType;
use App\Models\TechnicianRequest;
use App\Models\TechnicianRequestPriority;
use App\Models\TechnicianRequestStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TechnicianRequestService
{
    public function __construct(
        private readonly NumberingPatternService $numbering,
        private readonly StatusChangeHistoryService $statusChanges,
    ) {}

    public function canAccess(Company $owner, TechnicianRequest $request): bool
    {
        return (int) $request->company_id === (int) $owner->id;
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null,
     *     technician_request_status_id?: string|null,
     *     technician_request_priority_id?: string|null,
     *     is_screening?: string|null,
     *     responsible_user_id?: string|null,
     *     created_from?: string|null,
     *     created_to?: string|null,
     *     due_from?: string|null,
     *     due_to?: string|null
     * }  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $statusId = trim((string) ($filters['technician_request_status_id'] ?? ''));
        $priorityId = trim((string) ($filters['technician_request_priority_id'] ?? ''));
        $isScreening = trim((string) ($filters['is_screening'] ?? ''));
        $responsibleUserId = trim((string) ($filters['responsible_user_id'] ?? ''));
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $dueFrom = trim((string) ($filters['due_from'] ?? ''));
        $dueTo = trim((string) ($filters['due_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'code', 'due_at', 'resolved_at', 'created_at'],
            'id',
            'desc',
        );

        return TechnicianRequest::query()
            ->with([
                'status:id,name,color,is_open,kind',
                'priority:id,name,color,key',
                'responsibleUser:id,name',
            ])
            ->where('company_id', $owner->id)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('technician_requests.id', 'like', "%{$search}%")
                        ->orWhere('technician_requests.code', 'like', "%{$search}%")
                        ->orWhere('technician_requests.description', 'like', "%{$search}%")
                        ->orWhere('technician_requests.city', 'like', "%{$search}%")
                        ->orWhere('technician_requests.address_line', 'like', "%{$search}%");
                });
            })
            ->when($statusId !== '', fn (Builder $query) => $query->where('technician_request_status_id', (int) $statusId))
            ->when($priorityId !== '', fn (Builder $query) => $query->where('technician_request_priority_id', (int) $priorityId))
            ->when($isScreening === '1' || $isScreening === '0', fn (Builder $query) => $query->where('is_screening', $isScreening === '1'))
            ->when($responsibleUserId !== '', fn (Builder $query) => $query->where('responsible_user_id', (int) $responsibleUserId))
            ->when($createdFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $createdTo))
            ->when($dueFrom !== '', fn (Builder $query) => $query->whereDate('due_at', '>=', $dueFrom))
            ->when($dueTo !== '', fn (Builder $query) => $query->whereDate('due_at', '<=', $dueTo))
            ->orderBy("technician_requests.{$sort}", $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TechnicianRequest $request): array => $this->toListItem($request));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $owner, User $actor, array $data): TechnicianRequest
    {
        return DB::transaction(function () use ($owner, $actor, $data): TechnicianRequest {
            $isScreening = (bool) ($data['is_screening'] ?? false);
            $priorityId = isset($data['technician_request_priority_id'])
                ? (int) $data['technician_request_priority_id']
                : null;

            $attributes = $this->attributes($data);
            $attributes['company_id'] = $owner->id;
            $attributes['is_screening'] = $isScreening;
            $attributes['requester_user_id'] = $actor->id;
            $attributes['technician_request_status_id'] = $attributes['technician_request_status_id']
                ?? $this->defaultStatusId($isScreening);
            $attributes['due_at'] = $attributes['due_at'] ?? $this->computeDueAt($priorityId);
            $attributes['code'] = $this->allocateCode($owner);

            $request = TechnicianRequest::query()->create($attributes);
            $this->syncServiceTypes($request, $data['service_type_ids'] ?? []);

            $this->statusChanges->record(
                ChatDocumentType::TechnicianRequest,
                (int) $request->id,
                null,
                $request->technician_request_status_id !== null
                    ? (int) $request->technician_request_status_id
                    : null,
                $actor,
            );

            return $request->fresh($this->defaultRelations()) ?? $request;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TechnicianRequest $request, array $data): TechnicianRequest
    {
        return DB::transaction(function () use ($request, $data): TechnicianRequest {
            $previousStatusId = $request->technician_request_status_id !== null
                ? (int) $request->technician_request_status_id
                : null;

            $attributes = $this->attributes($data);
            // Screening flag and parent link are not editable via header update.
            unset($attributes['is_screening'], $attributes['parent_technician_request_id'], $attributes['requester_user_id']);

            $nextStatusId = isset($attributes['technician_request_status_id'])
                ? (int) $attributes['technician_request_status_id']
                : $previousStatusId;

            $attributes['resolved_at'] = $this->resolveResolvedAt(
                $request->resolved_at,
                $previousStatusId,
                $nextStatusId,
            );

            $request->update($attributes);
            $this->syncServiceTypes($request, $data['service_type_ids'] ?? []);

            $fresh = $request->fresh($this->defaultRelations()) ?? $request;

            $this->statusChanges->record(
                ChatDocumentType::TechnicianRequest,
                (int) $fresh->id,
                $previousStatusId,
                $fresh->technician_request_status_id !== null
                    ? (int) $fresh->technician_request_status_id
                    : null,
            );

            return $fresh;
        });
    }

    public function delete(TechnicianRequest $request): void
    {
        if ($request->trashed()) {
            return;
        }

        DB::transaction(function () use ($request): void {
            $request->delete();
        });
    }

    public function cancel(TechnicianRequest $request): TechnicianRequest
    {
        if ($request->is_screening) {
            throw ValidationException::withMessages([
                'status' => 'Screening requests cannot be cancelled with the request cancel action.',
            ]);
        }

        $status = $request->status;

        if ($status !== null && ! $status->is_open) {
            throw ValidationException::withMessages([
                'status' => 'Only open requests can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($request): TechnicianRequest {
            $oldStatusId = $request->technician_request_status_id !== null
                ? (int) $request->technician_request_status_id
                : null;

            $request->update([
                'technician_request_status_id' => TechnicianRequestStatusId::RequestCancelled->value,
                'resolved_at' => $request->resolved_at ?? now(),
            ]);

            $fresh = $request->fresh($this->defaultRelations()) ?? $request;

            $this->statusChanges->record(
                ChatDocumentType::TechnicianRequest,
                (int) $fresh->id,
                $oldStatusId,
                TechnicianRequestStatusId::RequestCancelled->value,
            );

            return $fresh;
        });
    }

    public function createScreening(TechnicianRequest $parent, Company $owner): TechnicianRequest
    {
        if ((int) $parent->company_id !== (int) $owner->id) {
            throw ValidationException::withMessages([
                'parent' => 'The parent request does not belong to the active company.',
            ]);
        }

        if ($parent->is_screening) {
            throw ValidationException::withMessages([
                'parent' => 'Screenings cannot be created from another screening.',
            ]);
        }

        return DB::transaction(function () use ($parent, $owner): TechnicianRequest {
            $parent->loadMissing('serviceTypes');

            $screening = TechnicianRequest::query()->create([
                'company_id' => $owner->id,
                'is_screening' => true,
                'parent_technician_request_id' => $parent->id,
                'work_order_id' => $parent->work_order_id,
                'code' => $this->allocateCode($owner),
                'description' => $parent->description,
                'notes' => $parent->notes,
                'internal_notes' => $parent->internal_notes,
                'city' => $parent->city,
                'postal_code' => $parent->postal_code,
                'address_line' => $parent->address_line,
                'province_name' => $parent->province_name,
                'country_id' => $parent->country_id,
                'language_id' => $parent->language_id,
                'requester_user_id' => $parent->requester_user_id,
                'responsible_user_id' => $parent->responsible_user_id,
                'resolved_at' => null,
                'due_at' => $this->computeDueAt(
                    $parent->technician_request_priority_id !== null
                        ? (int) $parent->technician_request_priority_id
                        : null,
                ),
                'next_action_at' => null,
                'technician_request_priority_id' => $parent->technician_request_priority_id,
                'technician_request_status_id' => TechnicianRequestStatusId::ScreeningOpen->value,
            ]);

            $this->syncServiceTypes(
                $screening,
                $parent->serviceTypes->pluck('id')->map(fn ($id) => (int) $id)->all(),
            );

            $fresh = $screening->fresh($this->defaultRelations()) ?? $screening;

            $this->statusChanges->record(
                ChatDocumentType::TechnicianRequest,
                (int) $fresh->id,
                null,
                $fresh->technician_request_status_id !== null
                    ? (int) $fresh->technician_request_status_id
                    : null,
            );

            return $fresh;
        });
    }

    public function attachTechnician(
        TechnicianRequest $request,
        Company $owner,
        int $companyRelationshipId,
    ): TechnicianRequest {
        $relationship = $this->assertAccessibleTechnician($owner, $companyRelationshipId);

        return DB::transaction(function () use ($request, $relationship): TechnicianRequest {
            $request->technicians()->syncWithoutDetaching([$relationship->id]);

            $statusId = $request->technician_request_status_id !== null
                ? (int) $request->technician_request_status_id
                : null;

            if (in_array($statusId, [
                TechnicianRequestStatusId::RequestOpen->value,
                TechnicianRequestStatusId::RequestInProgress->value,
            ], true)) {
                $request->update([
                    'technician_request_status_id' => TechnicianRequestStatusId::RequestFinished->value,
                    'resolved_at' => $request->resolved_at ?? now(),
                ]);

                $this->statusChanges->record(
                    ChatDocumentType::TechnicianRequest,
                    (int) $request->id,
                    $statusId,
                    TechnicianRequestStatusId::RequestFinished->value,
                );
            }

            return $request->fresh($this->defaultRelations()) ?? $request;
        });
    }

    public function detachTechnician(
        TechnicianRequest $request,
        Company $owner,
        int $companyRelationshipId,
    ): TechnicianRequest {
        $this->assertAccessibleTechnician($owner, $companyRelationshipId);

        return DB::transaction(function () use ($request, $companyRelationshipId): TechnicianRequest {
            $request->technicians()->detach($companyRelationshipId);

            return $request->fresh($this->defaultRelations()) ?? $request;
        });
    }

    /**
     * @return list<array{id: int, label: string, color: string|null, kind: string, is_open: bool}>
     */
    public function statusOptions(?string $kind = null, ?int $includeId = null): array
    {
        return TechnicianRequestStatus::query()
            ->when(
                $kind !== null && in_array($kind, TechnicianRequestStatusKind::values(), true),
                fn (Builder $query) => $query->where('kind', $kind),
            )
            ->where(function (Builder $query) use ($includeId): void {
                $query->where('is_open', true);

                if ($includeId !== null) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('kind')
            ->orderBy('lifecycle')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'kind', 'is_open'])
            ->map(fn (TechnicianRequestStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'kind' => $status->kind->value,
                'is_open' => (bool) $status->is_open,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, color: string|null, key: string}>
     */
    public function priorityOptions(): array
    {
        return TechnicianRequestPriority::query()
            ->orderBy('id')
            ->get(['id', 'name', 'color', 'key'])
            ->map(fn (TechnicianRequestPriority $priority): array => [
                'id' => $priority->id,
                'label' => $priority->name,
                'color' => $priority->color,
                'key' => $priority->key->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Seed user options. Full lists load via /select-options/users.
     *
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(Company $owner, array $includeUserIds = []): array
    {
        unset($owner);

        return CompanyMemberUsers::optionsByIds($includeUserIds);
    }

    /**
     * Limited member list for index filter selects.
     *
     * @return list<array{id: int, label: string}>
     */
    public function userFilterOptions(Company $owner): array
    {
        return CompanyMemberUsers::searchOptions($owner, limit: 100);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function languageOptions(): array
    {
        return Language::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'label' => $language->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function countryOptions(): array
    {
        return Country::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Country $country): array => [
                'id' => $country->id,
                'label' => $country->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function serviceTypeOptions(): array
    {
        return ServiceType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ServiceType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Seed technician options. Full lists load via /select-options/technicians.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    public function technicianOptions(Company $owner, array $includeIds = []): array
    {
        return app(\App\Domain\Companies\Services\EstablishmentService::class)
            ->technicianOptions($owner, $includeIds);
    }

    /**
     * Seed work-order options. Full lists load via /select-options/work-orders.
     *
     * @return list<array{id: int, label: string}>
     */
    public function workOrderOptions(Company $owner, ?int $includeId = null): array
    {
        return $this->searchWorkOrderOptions(
            $owner,
            includeIds: $includeId !== null && $includeId > 0 ? [$includeId] : [],
            onlyIncludeIds: true,
        );
    }

    /**
     * Lightweight options for async work-order pickers.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    public function searchWorkOrderOptions(
        Company $owner,
        ?string $search = null,
        array $includeIds = [],
        ?int $limit = 50,
        bool $onlyIncludeIds = false,
    ): array {
        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($onlyIncludeIds) {
            if ($includeIds === []) {
                return [];
            }

            return WorkOrder::query()
                ->where('owner_company_id', $owner->id)
                ->whereIn('id', $includeIds)
                ->orderByDesc('id')
                ->get(['id', 'code', 'subject'])
                ->map(fn (WorkOrder $workOrder): array => $this->workOrderSelectOption($workOrder))
                ->values()
                ->all();
        }

        $needle = trim((string) $search);

        $rows = WorkOrder::query()
            ->where('owner_company_id', $owner->id)
            ->when($needle !== '', function (Builder $query) use ($needle): void {
                $query->where(function (Builder $inner) use ($needle): void {
                    $inner->where('code', 'like', "%{$needle}%")
                        ->orWhere('subject', 'like', "%{$needle}%")
                        ->orWhere('reference', 'like', "%{$needle}%")
                        ->orWhere('id', 'like', "%{$needle}%");
                });
            })
            ->orderByDesc('id')
            ->limit(max(1, min($limit ?? 50, 100)))
            ->get(['id', 'code', 'subject'])
            ->map(fn (WorkOrder $workOrder): array => $this->workOrderSelectOption($workOrder))
            ->values()
            ->all();

        if ($includeIds !== []) {
            $present = array_map(fn (array $row): int => (int) $row['id'], $rows);
            $missing = array_values(array_diff($includeIds, $present));

            if ($missing !== []) {
                $extra = WorkOrder::query()
                    ->where('owner_company_id', $owner->id)
                    ->whereIn('id', $missing)
                    ->get(['id', 'code', 'subject'])
                    ->map(fn (WorkOrder $workOrder): array => $this->workOrderSelectOption($workOrder))
                    ->all();

                $rows = array_values(array_merge($extra, $rows));
            }
        }

        return $rows;
    }

    /**
     * @return array{id: int, label: string}
     */
    private function workOrderSelectOption(WorkOrder $workOrder): array
    {
        return [
            'id' => $workOrder->id,
            'label' => $workOrder->code
                ? "#{$workOrder->id} — {$workOrder->code}"
                : "#{$workOrder->id}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(TechnicianRequest $request): array
    {
        $request->loadMissing([
            'status',
            'priority',
            'serviceTypes:id,name',
            'technicians.relatedCompany:id,name,tradename,logo',
            'screenings.status:id,name,color',
            'requesterUser:id,name',
            'responsibleUser:id,name',
            'workOrder:id,code',
            'country:id,name',
            'language:id,name',
            'parent:id,code',
        ]);

        return [
            'id' => $request->id,
            'company_id' => $request->company_id,
            'is_screening' => (bool) $request->is_screening,
            'parent_technician_request_id' => $request->parent_technician_request_id,
            'parent_code' => $request->parent?->code,
            'work_order_id' => $request->work_order_id,
            'code' => $request->code,
            'description' => $request->description,
            'notes' => $request->notes,
            'internal_notes' => $request->internal_notes,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'address_line' => $request->address_line,
            'province_name' => $request->province_name,
            'country_id' => $request->country_id,
            'language_id' => $request->language_id,
            'requester_user_id' => $request->requester_user_id,
            'requester_name' => $request->requesterUser?->name,
            'responsible_user_id' => $request->responsible_user_id,
            'resolved_at' => $request->resolved_at?->format('Y-m-d H:i:s'),
            'due_at' => $request->due_at?->format('Y-m-d H:i:s'),
            'next_action_at' => $request->next_action_at?->format('Y-m-d H:i:s'),
            'technician_request_priority_id' => $request->technician_request_priority_id,
            'technician_request_status_id' => $request->technician_request_status_id,
            'status_name' => $request->status?->name,
            'status_is_open' => (bool) ($request->status?->is_open ?? false),
            'service_type_ids' => $request->serviceTypes->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'technicians' => $request->technicians->map(function (CompanyRelationship $relationship): array {
                $company = $relationship->relatedCompany;

                return [
                    'id' => $relationship->id,
                    'label' => $company?->tradename ?: $company?->name ?: "#{$relationship->id}",
                ];
            })->values()->all(),
            'screenings' => $request->screenings->map(fn (TechnicianRequest $screening): array => [
                'id' => $screening->id,
                'code' => $screening->code,
                'status_name' => $screening->status?->name,
                'status_color' => $screening->status?->color,
                'created_at' => $screening->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(TechnicianRequest $request): array
    {
        return [
            'id' => $request->id,
            'code' => $request->code,
            'description' => $request->description,
            'is_screening' => (bool) $request->is_screening,
            'status_name' => $request->status?->name,
            'status_color' => $request->status?->color,
            'priority_name' => $request->priority?->name,
            'priority_color' => $request->priority?->color,
            'responsible_name' => $request->responsibleUser?->name,
            'city' => $request->city,
            'due_at' => $request->due_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'resolved_at' => $request->resolved_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'created_at' => $request->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    public function computeDueAt(?int $priorityId, ?CarbonImmutable $from = null): ?CarbonImmutable
    {
        if ($priorityId === null) {
            return null;
        }

        $priority = TechnicianRequestPriority::query()->find($priorityId);

        if ($priority === null) {
            return null;
        }

        $from ??= CarbonImmutable::now();

        return match ($priority->key) {
            TechnicianRequestPriorityKey::Urgent => $from->addHours(3),
            TechnicianRequestPriorityKey::High => $this->addWeekdays($from, 3),
            TechnicianRequestPriorityKey::Medium => $this->addWeekdays($from, 7),
            TechnicianRequestPriorityKey::Low => $this->addWeekdays($from, 14),
        };
    }

    private function addWeekdays(CarbonImmutable $from, int $days): CarbonImmutable
    {
        $cursor = $from;
        $remaining = $days;

        while ($remaining > 0) {
            $cursor = $cursor->addDay();

            if (! $cursor->isWeekend()) {
                $remaining--;
            }
        }

        return $cursor;
    }

    private function allocateCode(Company $owner): ?string
    {
        $resource = NumberingResource::TechnicianRequests->value;
        $existing = $this->numbering->findForResource($owner, $resource);

        if ($existing !== null && ! $existing->is_active) {
            return null;
        }

        $allocated = $this->numbering->allocateNext($owner, $resource);

        return is_string($allocated) && $allocated !== '' ? $allocated : null;
    }

    private function defaultStatusId(bool $isScreening): int
    {
        return $isScreening
            ? TechnicianRequestStatusId::ScreeningOpen->value
            : TechnicianRequestStatusId::RequestOpen->value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'is_screening' => (bool) ($data['is_screening'] ?? false),
            'parent_technician_request_id' => $data['parent_technician_request_id'] ?? null,
            'work_order_id' => $data['work_order_id'] ?? null,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'address_line' => $data['address_line'] ?? null,
            'province_name' => $data['province_name'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'language_id' => $data['language_id'] ?? null,
            'requester_user_id' => $data['requester_user_id'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'next_action_at' => $data['next_action_at'] ?? null,
            'technician_request_priority_id' => $data['technician_request_priority_id'] ?? null,
            'technician_request_status_id' => $data['technician_request_status_id'] ?? null,
        ];
    }

    /**
     * @param  list<int|string>  $serviceTypeIds
     */
    private function syncServiceTypes(TechnicianRequest $request, array $serviceTypeIds): void
    {
        $ids = collect($serviceTypeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $request->serviceTypes()->sync($ids);
    }

    private function resolveResolvedAt(
        mixed $currentResolvedAt,
        ?int $previousStatusId,
        ?int $nextStatusId,
    ): mixed {
        $wasClosed = $previousStatusId !== null
            && in_array($previousStatusId, TechnicianRequestStatusId::closedIds(), true);
        $isClosed = $nextStatusId !== null
            && in_array($nextStatusId, TechnicianRequestStatusId::closedIds(), true);

        if ($isClosed) {
            return $currentResolvedAt ?? now();
        }

        if ($wasClosed && ! $isClosed) {
            return null;
        }

        return $currentResolvedAt;
    }

    private function assertAccessibleTechnician(Company $owner, int $companyRelationshipId): CompanyRelationship
    {
        $relationship = CompanyRelationship::query()
            ->whereKey($companyRelationshipId)
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->first();

        if ($relationship === null) {
            throw ValidationException::withMessages([
                'company_relationship_id' => 'The selected technician is invalid for the active company.',
            ]);
        }

        return $relationship;
    }

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return [
            'status',
            'priority',
            'serviceTypes',
            'technicians.relatedCompany',
            'screenings.status',
            'requesterUser',
            'responsibleUser',
            'workOrder',
            'country',
            'language',
            'parent',
        ];
    }
}
