<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use App\Domain\QualityScores\Services\QualityScoreProcessor;
use App\Domain\StatusChanges\Services\StatusChangeHistoryService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Article;
use App\Models\ClientPriority;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\Establishment;
use App\Models\Requester;
use App\Models\TaskToPerform;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderTechnician;
use App\Models\WorkOrderType;
use App\Policies\EstimatePolicy;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WorkOrderService
{
    public function __construct(
        private readonly WorkOrderConfirmationService $confirmation,
        private readonly NumberingPatternService $numbering,
        private readonly QualityScoreProcessor $qualityScores,
        private readonly StatusChangeHistoryService $statusChanges,
        private readonly WorkOrderStatusCatalog $statuses,
    ) {}

    /**
     * @return list<int>
     */
    public function accessibleCompanyIds(Company $owner): array
    {
        $ids = $owner->ownedRelationships()
            ->where('kind', CompanyRelationshipKind::Customer->value)
            ->pluck('related_company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids[] = (int) $owner->id;

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array{id: int, label: string, company_id: int}>
     */
    /**
     * Active establishments for selects. Keep `$includeIds` so edit still shows saved inactive values.
     *
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function establishmentOptions(Company $owner, array $includeIds = []): array
    {
        $ids = $this->accessibleCompanyIds($owner);

        if ($ids === []) {
            return [];
        }

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        return Establishment::query()
            ->whereIn('company_id', $ids)
            ->where(function ($query) use ($includeIds): void {
                $query->where('is_active', true);

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
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
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    public function contractOptions(Company $owner, array $includeIds = []): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        if ($companyIds === []) {
            return [];
        }

        $includeIds = array_values(array_unique(array_filter(
            array_map('intval', $includeIds),
            fn (int $id): bool => $id > 0,
        )));

        return Contract::query()
            ->where(function ($query) use ($companyIds, $includeIds): void {
                $query->whereIn('company_id', $companyIds);

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderByDesc('id')
            ->get(['id', 'code', 'description', 'work_order_subject'])
            ->map(function (Contract $contract): array {
                $label = $contract->code
                    ?: $contract->description
                    ?: $contract->work_order_subject
                    ?: '#'.$contract->id;

                if ($contract->code && filled($contract->description)) {
                    $label = $contract->code.' — '.$contract->description;
                }

                return [
                    'id' => (int) $contract->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Prefill helpers when creating a document from `?contract_id=`.
     *
     * @return array{contract_id: int|null, establishment_id: int|null, subject: string|null}
     */
    public function defaultsFromContract(
        Company $owner,
        ?int $requestedContractId,
        ?int $requestedEstablishmentId = null,
    ): array {
        $contractId = null;

        if ($requestedContractId !== null && $requestedContractId > 0) {
            $allowed = collect($this->contractOptions($owner))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $contractId = in_array($requestedContractId, $allowed, true) ? $requestedContractId : null;
        }

        $establishmentId = null;

        if ($requestedEstablishmentId !== null && $requestedEstablishmentId > 0) {
            $allowedEstablishments = collect($this->establishmentOptions($owner))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $establishmentId = in_array($requestedEstablishmentId, $allowedEstablishments, true)
                ? $requestedEstablishmentId
                : null;
        }

        $subject = null;

        if ($contractId !== null) {
            $contract = Contract::query()
                ->with(['establishments' => fn ($query) => $query->select('establishments.id')])
                ->find($contractId);

            $subject = filled($contract?->work_order_subject) ? (string) $contract->work_order_subject : null;

            if ($establishmentId === null && $contract !== null) {
                $linkedIds = $contract->establishments->pluck('id')->map(fn ($id) => (int) $id)->all();
                $allowedEstablishments = collect($this->establishmentOptions($owner))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $candidates = array_values(array_intersect($linkedIds, $allowedEstablishments));

                if (count($candidates) === 1) {
                    $establishmentId = $candidates[0];
                }
            }
        }

        return [
            'contract_id' => $contractId,
            'establishment_id' => $establishmentId,
            'subject' => $subject,
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     color: string|null,
     *     is_open: bool,
     *     confirms_estimate: bool,
     *     rejects_to_estimate: bool,
     *     requires_confirmation: bool,
     *     requires_justification: bool
     * }>
     */
    public function statusOptions(WorkOrderStage $kind, ?int $currentStatusId = null): array
    {
        return $this->statuses->options($kind, $currentStatusId);
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function typeOptions(): array
    {
        return WorkOrderType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (WorkOrderType $type): array => [
                'id' => $type->id,
                'label' => $type->name,
                'color' => $type->color,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function priorityOptions(): array
    {
        return ClientPriority::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (ClientPriority $priority): array => [
                'id' => $priority->id,
                'label' => $priority->name,
                'color' => $priority->color,
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
     * @return list<array{id: int, label: string, company_id: int}>
     */
    public function requesterOptions(Company $owner, ?int $establishmentId = null): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        if ($establishmentId !== null) {
            $establishmentCompanyId = Establishment::query()->whereKey($establishmentId)->value('company_id');

            if ($establishmentCompanyId !== null) {
                $companyIds = [(int) $establishmentCompanyId];
            }
        }

        if ($companyIds === []) {
            return [];
        }

        return Requester::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('name')
            ->get(['id', 'name', 'company_id'])
            ->map(fn (Requester $requester): array => [
                'id' => $requester->id,
                'label' => $requester->name,
                'company_id' => (int) $requester->company_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function technicianOptions(Company $owner): array
    {
        return CompanyRelationship::query()
            ->with('relatedCompany:id,name,tradename,logo,is_active')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->whereHas('relatedCompany', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get()
            ->map(function (CompanyRelationship $relationship): array {
                $name = $relationship->relatedCompany?->tradename
                    ?: $relationship->relatedCompany?->name
                    ?: '#'.$relationship->id;

                return $relationship->toSelectOption($name);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function articleOptions(): array
    {
        return Article::query()
            ->orderBy('code')
            ->limit(200)
            ->get(['id', 'code'])
            ->map(fn (Article $article): array => [
                'id' => $article->id,
                'label' => $article->code,
            ])
            ->values()
            ->all();
    }

    public function defaultStatusId(WorkOrderStage $stage): ?int
    {
        return $this->statuses->defaultId($stage);
    }

    /**
     * @param  array{search?: string|null, stage?: string|null, pending?: string|null, establishment_id?: int|string|null, contract_id?: int|string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, WorkOrder>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $stage = trim((string) ($filters['stage'] ?? ''));
        $pending = trim((string) ($filters['pending'] ?? ''));
        $establishmentId = (int) ($filters['establishment_id'] ?? 0);
        $contractId = (int) ($filters['contract_id'] ?? 0);
        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort(
            $filters,
            ['id', 'code', 'subject', 'stage', 'created_at'],
            'id',
        );

        $companyIds = $this->accessibleCompanyIds($owner);

        return WorkOrder::query()
            ->with([
                'establishment',
                'status',
                'responsibleUser',
                'type',
                'priority',
                'technicians' => fn ($query) => $query->where('is_selected', true),
                'technicians.technician.relatedCompany',
            ])
            ->whereHas('establishment', function ($query) use ($companyIds): void {
                $query->whereIn('company_id', $companyIds);
            })
            ->when($establishmentId > 0, function ($query) use ($establishmentId): void {
                $query->where('establishment_id', $establishmentId);
            })
            ->when($contractId > 0, function ($query) use ($contractId): void {
                $query->where('contract_id', $contractId);
            })
            ->when($stage !== '' && in_array($stage, WorkOrderStage::values(), true), function ($query) use ($stage): void {
                $query->where('stage', $stage);
            })
            ->when($pending === '1' || $pending === '0', function ($query) use ($pending): void {
                $query->whereHas('status', function ($statusQuery) use ($pending): void {
                    $statusQuery->where('is_open', $pending === '1');
                });
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('subject', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('establishment', function ($establishmentQuery) use ($search): void {
                            $establishmentQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($createdFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, stage?: string|null, pending?: string|null, establishment_id?: int|string|null, contract_id?: int|string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (WorkOrder $workOrder): array => $this->toListItem($workOrder));
    }

    /**
     * Aggregate totals for an establishment (legacy `components.totales` for ots/presupuestos).
     *
     * @return array{count: int, total_amount: float, cost_amount: float, margin_percentage: float}
     */
    public function totalsForEstablishment(Company $owner, int $establishmentId, WorkOrderStage $stage): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        $row = WorkOrder::query()
            ->where('establishment_id', $establishmentId)
            ->where('stage', $stage->value)
            ->whereHas('establishment', function ($query) use ($companyIds): void {
                $query->whereIn('company_id', $companyIds);
            })
            ->selectRaw('COUNT(*) as quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(total_euros, total_amount, 0)), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(cost_amount, 0)), 0) as cost_amount')
            ->first();

        return $this->formatTotalsRow($row);
    }

    /**
     * Aggregate totals for work orders linked to a contract.
     *
     * @return array{count: int, total_amount: float, cost_amount: float, margin_percentage: float}
     */
    public function totalsForContract(Company $owner, int $contractId, WorkOrderStage $stage): array
    {
        $companyIds = $this->accessibleCompanyIds($owner);

        $row = WorkOrder::query()
            ->where('contract_id', $contractId)
            ->where('stage', $stage->value)
            ->whereHas('establishment', function ($query) use ($companyIds): void {
                $query->whereIn('company_id', $companyIds);
            })
            ->selectRaw('COUNT(*) as quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(total_euros, total_amount, 0)), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(cost_amount, 0)), 0) as cost_amount')
            ->first();

        return $this->formatTotalsRow($row);
    }

    /**
     * @param  object{quantity?: mixed, total_amount?: mixed, cost_amount?: mixed}|null  $row
     * @return array{count: int, total_amount: float, cost_amount: float, margin_percentage: float}
     */
    private function formatTotalsRow(?object $row): array
    {
        $total = round((float) ($row?->total_amount ?? 0), 2);
        $cost = round((float) ($row?->cost_amount ?? 0), 2);
        $margin = $total > 0.0 ? round((($total - $cost) / $total) * 100, 2) : 0.0;

        return [
            'count' => (int) ($row?->quantity ?? 0),
            'total_amount' => $total,
            'cost_amount' => $cost,
            'margin_percentage' => $margin,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $owner, array $data): WorkOrder
    {
        return DB::transaction(function () use ($owner, $data): WorkOrder {
            $stage = WorkOrderStage::from((string) $data['stage']);
            $attributes = $this->attributes($data, $stage);

            if (($attributes['code'] ?? null) === null) {
                $attributes['code'] = $this->allocateCode($owner, $stage);
            }

            if (($attributes['billing_company_id'] ?? null) === null) {
                $attributes['billing_company_id'] = Establishment::query()
                    ->whereKey($attributes['establishment_id'])
                    ->value('company_id');
            }

            if ($stage === WorkOrderStage::WorkOrder) {
                $attributes['confirmed_at'] = now();
            }

            $workOrder = WorkOrder::query()->create($attributes);
            $this->syncChildren($workOrder, $data);

            $createdStatusId = $workOrder->status_id !== null ? (int) $workOrder->status_id : null;
            $this->statusChanges->record(
                ChatDocumentType::WorkOrder,
                (int) $workOrder->id,
                null,
                $createdStatusId,
            );

            if ($this->statuses->statusConfirmsEstimate($workOrder->status_id !== null ? (int) $workOrder->status_id : null) && $workOrder->isEstimate()) {
                $this->confirmation->confirm($workOrder, $this->statuses->postConfirmDefaultId(), $owner);
                $confirmed = $workOrder->fresh() ?? $workOrder;
                $this->statusChanges->record(
                    ChatDocumentType::WorkOrder,
                    (int) $confirmed->id,
                    $createdStatusId,
                    $confirmed->status_id !== null ? (int) $confirmed->status_id : null,
                );
            }

            $fresh = $workOrder->fresh($this->defaultRelations()) ?? $workOrder;
            $this->applyStatusSideEffects($fresh, $createdStatusId ?? 0, true);
            $fresh = $fresh->fresh($this->defaultRelations()) ?? $fresh;
            $this->qualityScores->handleEstimateSent($fresh, null);

            return $fresh;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{work_order: WorkOrder, cloned_estimate: WorkOrder|null}
     */
    public function update(Company $owner, WorkOrder $workOrder, array $data, ?User $actor = null): array
    {
        return DB::transaction(function () use ($owner, $workOrder, $data, $actor): array {
            $workOrder->loadMissing('status');
            $newStatusId = (int) $data['status_id'];
            $oldStatusId = (int) $workOrder->status_id;
            $previousSentAt = $workOrder->sent_at?->toDateTimeString();
            $wasOpen = (bool) ($workOrder->status?->is_open ?? true);
            $fieldsLocked = ! $wasOpen && ! $this->actorCanUpdateClosed($actor, $workOrder);
            $approving = $workOrder->isEstimate() && $this->statuses->statusConfirmsEstimate($newStatusId);
            $rejecting = $workOrder->isConfirmedWorkOrder() && $this->statuses->statusRejectsToEstimate($newStatusId);

            if ($newStatusId !== $oldStatusId && ! $this->statuses->canTransition($oldStatusId, $newStatusId)) {
                throw ValidationException::withMessages([
                    'status_id' => 'This status change is not allowed.',
                ]);
            }

            $justification = trim((string) ($data['status_justification'] ?? ''));

            if ($newStatusId !== $oldStatusId) {
                $edge = $this->statuses->transition($oldStatusId, $newStatusId);

                if ($edge?->requires_justification && mb_strlen($justification) < 10) {
                    throw ValidationException::withMessages([
                        'status_justification' => 'A justification of at least 10 characters is required.',
                    ]);
                }
            }

            if ($fieldsLocked) {
                $this->assertClosedFieldsUnchanged($workOrder, $data);
            }

            $attributes = $this->attributes($data, $workOrder->stage);

            if ($approving || $rejecting) {
                unset($attributes['status_id']);
            }

            if ($fieldsLocked) {
                $attributes = array_intersect_key($attributes, ['status_id' => true]);
            }

            if ($attributes !== []) {
                $workOrder->update($attributes);
            }

            if (! $fieldsLocked) {
                $this->syncChildren($workOrder, $data);
            }

            $clone = null;

            if ($approving) {
                $this->confirmation->confirm(
                    $workOrder->fresh() ?? $workOrder,
                    $this->statuses->postConfirmDefaultId(),
                    $owner,
                );
            } elseif ($rejecting) {
                $clone = $this->rejectToEstimate($owner, $workOrder->fresh() ?? $workOrder);
            } elseif ($newStatusId !== $oldStatusId) {
                $this->assertStatusMatchesStage($workOrder->stage, $newStatusId);
                $workOrder->forceFill(['status_id' => $newStatusId])->save();
            }

            $fresh = $workOrder->fresh($this->defaultRelations()) ?? $workOrder;
            $this->applyStatusSideEffects($fresh, $oldStatusId, $wasOpen);
            $fresh = $fresh->fresh($this->defaultRelations()) ?? $fresh;

            $finalStatusId = $fresh->status_id !== null ? (int) $fresh->status_id : null;
            $this->statusChanges->record(
                ChatDocumentType::WorkOrder,
                (int) $fresh->id,
                $oldStatusId,
                $finalStatusId,
                $actor,
                $justification !== '' ? $justification : null,
            );

            if ($clone !== null) {
                $this->statusChanges->record(
                    ChatDocumentType::WorkOrder,
                    (int) $clone->id,
                    null,
                    $clone->status_id !== null ? (int) $clone->status_id : null,
                    $actor,
                );
            }

            $this->qualityScores->handleEstimateSent($fresh, $previousSentAt);

            $isOpen = (bool) ($fresh->status?->is_open ?? true);
            $this->qualityScores->handleWorkOrderClosed($fresh, $wasOpen, $isOpen);

            return [
                'work_order' => $fresh,
                'cloned_estimate' => $clone,
            ];
        });
    }

    public function delete(WorkOrder $workOrder): void
    {
        if ($workOrder->trashed()) {
            return;
        }

        $workOrder->delete();
    }

    public function rejectToEstimate(Company $owner, WorkOrder $workOrder): WorkOrder
    {
        if (! $workOrder->isConfirmedWorkOrder()) {
            throw ValidationException::withMessages([
                'status_id' => 'Only a work order can be rejected back to an estimate.',
            ]);
        }

        $estimateStatusId = $this->defaultStatusId(WorkOrderStage::Estimate);
        $rejectedStatusId = $this->statuses->rejectsToEstimateId();

        if ($estimateStatusId === null || $rejectedStatusId === null) {
            throw ValidationException::withMessages([
                'status_id' => 'Configure default and reject-to-estimate statuses first.',
            ]);
        }

        $workOrder->forceFill([
            'status_id' => $rejectedStatusId,
        ])->save();

        $clone = $workOrder->replicate([
            'public_id',
            'code',
            'stage',
            'confirmed_at',
            'status_id',
            'source_work_order_id',
            'closed_at',
            'billed_at',
            'legacy_erp_id',
        ]);
        $clone->public_id = (string) Str::uuid();
        $clone->stage = WorkOrderStage::Estimate;
        $clone->confirmed_at = null;
        $clone->status_id = $estimateStatusId;
        $clone->source_work_order_id = $workOrder->id;
        $code = $workOrder->code ?: (string) $workOrder->id;
        $clone->subject = trim((string) $workOrder->subject).' (viene de '.$code.')';
        $clone->code = $this->allocateCode($owner, WorkOrderStage::Estimate);

        $clone->save();
        $clone->collaborators()->sync($workOrder->collaborators()->pluck('users.id')->all());

        TaskToPerform::query()
            ->where('document_id', $workOrder->id)
            ->where('document_type', TaskDocumentType::WorkOrder->value)
            ->get()
            ->each(function (TaskToPerform $task) use ($clone): void {
                $copy = $task->replicate();
                $copy->document_type = TaskDocumentType::Estimate;
                $copy->document_id = $clone->id;
                $copy->is_completed = false;
                $copy->save();
            });

        return $clone->fresh($this->defaultRelations()) ?? $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing(['status', 'lines', 'technicians', 'collaborators', 'sourceWorkOrder:id,code,subject']);

        return [
            'id' => $workOrder->id,
            'public_id' => $workOrder->public_id,
            'code' => $workOrder->code,
            'subject' => $workOrder->subject,
            'reference' => $workOrder->reference,
            'purchase_order' => $workOrder->purchase_order,
            'stage' => $workOrder->stage instanceof WorkOrderStage
                ? $workOrder->stage->value
                : (string) $workOrder->stage,
            'confirmed_at' => $workOrder->confirmed_at?->toIso8601String(),
            'source_work_order_id' => $workOrder->source_work_order_id,
            'source_work_order_label' => $workOrder->sourceWorkOrder?->code
                ?: $workOrder->sourceWorkOrder?->subject,
            'status_id' => $workOrder->status_id,
            'status_is_open' => (bool) ($workOrder->status?->is_open ?? true),
            'work_order_type_id' => $workOrder->work_order_type_id,
            'client_priority_id' => $workOrder->client_priority_id,
            'is_urgent' => $workOrder->is_urgent,
            'establishment_id' => $workOrder->establishment_id,
            'contract_id' => $workOrder->contract_id,
            'billing_company_id' => $workOrder->billing_company_id,
            'responsible_user_id' => $workOrder->responsible_user_id,
            'requester_id' => $workOrder->requester_id,
            'notes' => $workOrder->notes,
            'internal_notes' => $workOrder->internal_notes,
            'received_at' => $workOrder->received_at?->format('Y-m-d\TH:i'),
            'intervention_at' => $workOrder->intervention_at?->format('Y-m-d\TH:i'),
            'due_at' => $workOrder->due_at?->format('Y-m-d\TH:i'),
            'collaborator_ids' => $workOrder->collaborators->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'lines' => $workOrder->lines->map(fn (WorkOrderLine $line): array => [
                'id' => $line->id,
                'article_id' => $line->article_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
            ])->values()->all(),
            'technicians' => $workOrder->technicians->map(fn (WorkOrderTechnician $technician): array => [
                'id' => $technician->id,
                'company_relationship_id' => $technician->company_relationship_id,
                'is_selected' => $technician->is_selected,
                'quote_net_amount' => $technician->quote_net_amount,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(WorkOrder $workOrder): array
    {
        $totalEuros = $workOrder->total_euros !== null
            ? (float) $workOrder->total_euros
            : ($workOrder->total_amount !== null ? (float) $workOrder->total_amount : null);
        $costAmount = $workOrder->cost_amount !== null ? (float) $workOrder->cost_amount : null;
        $marginPercentage = null;

        if ($totalEuros !== null && $totalEuros > 0.0 && $costAmount !== null) {
            $marginPercentage = round((($totalEuros - $costAmount) / $totalEuros) * 100, 2);
        }

        $selectedTechnician = $workOrder->relationLoaded('technicians')
            ? $workOrder->technicians->firstWhere('is_selected', true) ?? $workOrder->technicians->first()
            : null;
        $technicianCompany = $selectedTechnician?->technician?->relatedCompany;

        return [
            'id' => $workOrder->id,
            'code' => $workOrder->code,
            'subject' => $workOrder->subject,
            'stage' => $workOrder->stage instanceof WorkOrderStage
                ? $workOrder->stage->value
                : (string) $workOrder->stage,
            'establishment_name' => $workOrder->establishment?->name,
            'status_name' => $workOrder->status?->name,
            'status_color' => $workOrder->status?->color,
            'priority_name' => $workOrder->priority?->name,
            'priority_color' => $workOrder->priority?->color,
            'type_name' => $workOrder->type?->name,
            'type_color' => $workOrder->type?->color,
            'responsible_user_name' => $workOrder->responsibleUser?->name,
            'technician_name' => $technicianCompany?->tradename ?: $technicianCompany?->name,
            'is_urgent' => $workOrder->is_urgent,
            'total_euros' => $totalEuros,
            'cost_amount' => $costAmount,
            'margin_percentage' => $marginPercentage,
            'intervention_at' => $workOrder->intervention_at?->toIso8601String(),
            'expected_close_at' => $workOrder->expected_close_at?->toIso8601String(),
            'closed_at' => $workOrder->closed_at?->toIso8601String(),
            'created_at' => $workOrder->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, WorkOrderStage $stage): array
    {
        $statusId = isset($data['status_id']) ? (int) $data['status_id'] : null;

        if ($statusId !== null && ! (
            ($stage === WorkOrderStage::Estimate && $this->statuses->statusConfirmsEstimate($statusId))
            || ($stage === WorkOrderStage::WorkOrder && $this->statuses->statusRejectsToEstimate($statusId))
        )) {
            $this->assertStatusMatchesStage($stage, $statusId);
        }

        return [
            'code' => $data['code'] ?? null,
            'subject' => $data['subject'] ?? null,
            'reference' => $data['reference'] ?? null,
            'purchase_order' => $data['purchase_order'] ?? null,
            'stage' => $stage,
            'status_id' => $statusId,
            'work_order_type_id' => $data['work_order_type_id'] ?? null,
            'client_priority_id' => $data['client_priority_id'] ?? null,
            'is_urgent' => (bool) ($data['is_urgent'] ?? false),
            'establishment_id' => $data['establishment_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
            'billing_company_id' => $data['billing_company_id'] ?? null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'requester_id' => $data['requester_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'received_at' => $data['received_at'] ?? null,
            'intervention_at' => $data['intervention_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ];
    }

    private function applyStatusSideEffects(WorkOrder $workOrder, int $oldStatusId, bool $wasOpen): void
    {
        $statusId = $workOrder->status_id !== null ? (int) $workOrder->status_id : null;
        $payload = [];

        if (
            $workOrder->isEstimate()
            && $this->statuses->statusSetsSentAt($statusId)
            && $workOrder->sent_at === null
        ) {
            $payload['sent_at'] = now();
        }

        $isOpen = $this->statuses->isOpen($statusId);

        if (! $isOpen && $workOrder->closed_at === null) {
            $payload['closed_at'] = now();
        }

        if ($isOpen && ! $wasOpen) {
            $payload['closed_at'] = null;
        }

        if ($payload !== []) {
            $workOrder->forceFill($payload)->save();
        }
    }

    private function actorCanUpdateClosed(?User $actor, WorkOrder $workOrder): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($workOrder->isEstimate()) {
            return app(EstimatePolicy::class)->updateClosed($actor, $workOrder);
        }

        return $actor->can('updateClosed', $workOrder);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertClosedFieldsUnchanged(WorkOrder $workOrder, array $data): void
    {
        $comparisons = [
            'subject' => $workOrder->subject,
            'reference' => $workOrder->reference,
            'purchase_order' => $workOrder->purchase_order,
            'notes' => $workOrder->notes,
            'internal_notes' => $workOrder->internal_notes,
            'establishment_id' => $workOrder->establishment_id !== null ? (int) $workOrder->establishment_id : null,
            'contract_id' => $workOrder->contract_id !== null ? (int) $workOrder->contract_id : null,
            'work_order_type_id' => $workOrder->work_order_type_id !== null ? (int) $workOrder->work_order_type_id : null,
            'client_priority_id' => $workOrder->client_priority_id !== null ? (int) $workOrder->client_priority_id : null,
            'responsible_user_id' => $workOrder->responsible_user_id !== null ? (int) $workOrder->responsible_user_id : null,
            'requester_id' => $workOrder->requester_id !== null ? (int) $workOrder->requester_id : null,
        ];

        $errors = [];

        foreach ($comparisons as $key => $current) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $incoming = $data[$key];
            $incoming = $incoming === '' ? null : $incoming;
            $current = $current === '' ? null : $current;

            if ($incoming != $current) {
                $errors[$key] = 'This document is closed and cannot be edited.';
            }
        }

        if (array_key_exists('is_urgent', $data) && (bool) $data['is_urgent'] !== (bool) $workOrder->is_urgent) {
            $errors['is_urgent'] = 'This document is closed and cannot be edited.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncChildren(WorkOrder $workOrder, array $data): void
    {
        $collaboratorIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($data['collaborator_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        )));
        $workOrder->collaborators()->sync($collaboratorIds);

        $this->syncLines($workOrder, (array) ($data['lines'] ?? []));
        $this->syncTechnicians($workOrder, (array) ($data['technicians'] ?? []));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(WorkOrder $workOrder, array $lines): void
    {
        $kept = [];

        foreach (array_values($lines) as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $payload = [
                'article_id' => filled($line['article_id'] ?? null) ? (int) $line['article_id'] : null,
                'description' => filled($line['description'] ?? null) ? (string) $line['description'] : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'net_amount' => round($quantity * $unitPrice, 2),
                'sort_order' => $index + 1,
            ];

            $id = isset($line['id']) ? (int) $line['id'] : 0;
            $existing = $id > 0
                ? $workOrder->lines()->whereKey($id)->first()
                : null;

            if ($existing instanceof WorkOrderLine) {
                $existing->update($payload);
                $kept[] = $existing->id;
            } else {
                $created = $workOrder->lines()->create($payload);
                $kept[] = $created->id;
            }
        }

        $workOrder->lines()->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $technicians
     */
    private function syncTechnicians(WorkOrder $workOrder, array $technicians): void
    {
        $kept = [];

        foreach (array_values($technicians) as $row) {
            if (! is_array($row) || ! filled($row['company_relationship_id'] ?? null)) {
                continue;
            }

            $payload = [
                'company_relationship_id' => (int) $row['company_relationship_id'],
                'is_selected' => (bool) ($row['is_selected'] ?? false),
                'quote_net_amount' => filled($row['quote_net_amount'] ?? null) ? $row['quote_net_amount'] : null,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $existing = $id > 0
                ? $workOrder->technicians()->whereKey($id)->first()
                : $workOrder->technicians()
                    ->where('company_relationship_id', $payload['company_relationship_id'])
                    ->first();

            if ($existing instanceof WorkOrderTechnician) {
                $existing->update($payload);
                $kept[] = $existing->id;
            } else {
                $created = $workOrder->technicians()->create($payload);
                $kept[] = $created->id;
            }
        }

        $workOrder->technicians()->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
    }

    private function assertStatusMatchesStage(WorkOrderStage $stage, int $statusId): void
    {
        $kind = WorkOrderStatus::query()->whereKey($statusId)->value('kind');
        $kindValue = $kind instanceof WorkOrderStage ? $kind->value : (string) $kind;

        if ($kindValue !== $stage->value) {
            throw ValidationException::withMessages([
                'status_id' => 'The selected status does not match this document stage.',
            ]);
        }
    }

    public function numberingResourceFor(WorkOrderStage $stage): string
    {
        return $stage === WorkOrderStage::Estimate
            ? NumberingResource::Estimates->value
            : NumberingResource::WorkOrders->value;
    }

    public function allocateCode(Company $owner, WorkOrderStage $stage): ?string
    {
        $resource = $this->numberingResourceFor($stage);
        $existing = $this->numbering->findForResource($owner, $resource);

        if ($existing !== null && ! $existing->is_active) {
            return null;
        }

        $allocated = $this->numbering->allocateNext($owner, $resource);

        return is_string($allocated) && $allocated !== '' ? $allocated : null;
    }

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return ['establishment', 'status', 'responsibleUser', 'type', 'lines', 'technicians', 'collaborators'];
    }
}
