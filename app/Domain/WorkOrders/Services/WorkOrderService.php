<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use App\Domain\QualityScores\Services\QualityScoreProcessor;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Article;
use App\Models\ClientPriority;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\Requester;
use App\Models\TaskToPerform;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderTechnician;
use App\Models\WorkOrderType;
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
     * @return list<array{id: int, label: string, color: string|null}>
     */
    public function statusOptions(WorkOrderStage $kind): array
    {
        return WorkOrderStatus::query()
            ->kind($kind)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->get(['id', 'name', 'color'])
            ->map(fn (WorkOrderStatus $status): array => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
            ])
            ->values()
            ->all();
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
            ->with('relatedCompany:id,name,tradename')
            ->where('owner_company_id', $owner->id)
            ->where('kind', CompanyRelationshipKind::Technician->value)
            ->orderBy('id')
            ->get()
            ->map(function (CompanyRelationship $relationship): array {
                $name = $relationship->relatedCompany?->tradename
                    ?: $relationship->relatedCompany?->name
                    ?: '#'.$relationship->id;

                return [
                    'id' => $relationship->id,
                    'label' => $name,
                ];
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
        $preferred = $stage === WorkOrderStage::Estimate
            ? WorkOrder::DEFAULT_ESTIMATE_STATUS_ID
            : WorkOrder::DEFAULT_WORK_ORDER_STATUS_ID;

        $match = WorkOrderStatus::query()
            ->kind($stage)
            ->whereKey($preferred)
            ->value('id');

        if ($match !== null) {
            return (int) $match;
        }

        $open = WorkOrderStatus::query()
            ->kind($stage)
            ->orderBy('lifecycle')
            ->orderBy('id')
            ->value('id');

        return $open !== null ? (int) $open : null;
    }

    /**
     * @param  array{search?: string|null, stage?: string|null, pending?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, WorkOrder>
     */
    public function paginateForOwner(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $stage = trim((string) ($filters['stage'] ?? ''));
        $pending = trim((string) ($filters['pending'] ?? ''));
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
            ->with(['establishment', 'status', 'responsibleUser', 'type'])
            ->whereHas('establishment', function ($query) use ($companyIds): void {
                $query->whereIn('company_id', $companyIds);
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
     * @param  array{search?: string|null, stage?: string|null, pending?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null, created_from?: string|null, created_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(Company $owner, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginateForOwner($owner, $filters, $perPage)
            ->through(fn (WorkOrder $workOrder): array => $this->toListItem($workOrder));
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

            if ((int) $workOrder->status_id === WorkOrder::APPROVED_ESTIMATE_STATUS_ID && $workOrder->isEstimate()) {
                $this->confirmation->confirm($workOrder, null, $owner);
            }

            $fresh = $workOrder->fresh($this->defaultRelations()) ?? $workOrder;
            $this->qualityScores->handleEstimateSent($fresh, null);

            return $fresh;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{work_order: WorkOrder, cloned_estimate: WorkOrder|null}
     */
    public function update(Company $owner, WorkOrder $workOrder, array $data): array
    {
        return DB::transaction(function () use ($owner, $workOrder, $data): array {
            $workOrder->loadMissing('status');
            $newStatusId = (int) $data['status_id'];
            $oldStatusId = (int) $workOrder->status_id;
            $previousSentAt = $workOrder->sent_at?->toDateTimeString();
            $wasOpen = (bool) ($workOrder->status?->is_open ?? true);
            $approving = $workOrder->isEstimate() && $newStatusId === WorkOrder::APPROVED_ESTIMATE_STATUS_ID;
            $rejecting = $workOrder->isConfirmedWorkOrder() && $newStatusId === WorkOrder::REJECTED_TO_ESTIMATE_STATUS_ID;

            $attributes = $this->attributes($data, $workOrder->stage);

            if ($approving || $rejecting) {
                unset($attributes['status_id']);
            }

            $workOrder->update($attributes);
            $this->syncChildren($workOrder, $data);

            $clone = null;

            if ($approving) {
                $this->confirmation->confirm($workOrder->fresh() ?? $workOrder, null, $owner);
            } elseif ($rejecting) {
                $clone = $this->rejectToEstimate($owner, $workOrder->fresh() ?? $workOrder);
            } elseif ($newStatusId !== $oldStatusId) {
                $this->assertStatusMatchesStage($workOrder->stage, $newStatusId);
                $workOrder->forceFill(['status_id' => $newStatusId])->save();
            }

            $fresh = $workOrder->fresh($this->defaultRelations()) ?? $workOrder;

            if (
                $fresh->isEstimate()
                && (int) $fresh->status_id === 5
                && $fresh->sent_at === null
            ) {
                $fresh->forceFill(['sent_at' => now()])->save();
                $fresh = $fresh->fresh($this->defaultRelations()) ?? $fresh;
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

        $estimateStatusId = $this->defaultStatusId(WorkOrderStage::Estimate)
            ?? WorkOrder::DEFAULT_ESTIMATE_STATUS_ID;

        $workOrder->forceFill([
            'status_id' => WorkOrder::REJECTED_TO_ESTIMATE_STATUS_ID,
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
        $workOrder->loadMissing(['lines', 'technicians', 'collaborators', 'sourceWorkOrder:id,code,subject']);

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
            'work_order_type_id' => $workOrder->work_order_type_id,
            'client_priority_id' => $workOrder->client_priority_id,
            'is_urgent' => $workOrder->is_urgent,
            'establishment_id' => $workOrder->establishment_id,
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
            'type_name' => $workOrder->type?->name,
            'responsible_user_name' => $workOrder->responsibleUser?->name,
            'is_urgent' => $workOrder->is_urgent,
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
            ($stage === WorkOrderStage::Estimate && $statusId === WorkOrder::APPROVED_ESTIMATE_STATUS_ID)
            || ($stage === WorkOrderStage::WorkOrder && $statusId === WorkOrder::REJECTED_TO_ESTIMATE_STATUS_ID)
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
