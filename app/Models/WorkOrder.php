<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Legacy presupuesto Pendiente. */
    public const DEFAULT_ESTIMATE_STATUS_ID = 1;

    /** Legacy presupuesto Aprobado — confirms the estimate in place. */
    public const APPROVED_ESTIMATE_STATUS_ID = 7;

    /** Legacy OT Abierta - Establecimiento. */
    public const DEFAULT_WORK_ORDER_STATUS_ID = 10;

    /** Legacy OT Rechazada - Presupuesto — clones a new estimate. */
    public const REJECTED_TO_ESTIMATE_STATUS_ID = 12;

    /**
     * Legacy OT status after an estimate is approved (`EstadoOTEnum::RECIBIDA_OK_POR_ORGANIZAR`).
     */
    public const DEFAULT_CONFIRMED_STATUS_ID = 14;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'code',
        'subject',
        'reference',
        'purchase_order',
        'stage',
        'confirmed_at',
        'source_work_order_id',
        'status_id',
        'work_order_type_id',
        'client_priority_id',
        'is_urgent',
        'establishment_id',
        'billing_company_id',
        'responsible_user_id',
        'requester_id',
        'delegation_id',
        'currency_id',
        'contract_id',
        'contract_iteration_id',
        'incident_id',
        'evaluation_id',
        'parent_work_order_id',
        'invoicing_work_order_id',
        'sync_grouping',
        'is_intercompany',
        'notes',
        'internal_notes',
        'notes_alert',
        'internal_notes_alert',
        'is_reviewed',
        'received_at',
        'intervention_at',
        'assigned_at',
        'due_at',
        'expected_close_at',
        'sla_at',
        'closed_at',
        'billed_at',
        'sent_at',
        'received_at_overridden',
        'sla_justification',
        'is_sla_reviewed',
        'tax_rate',
        'tax_included',
        'net_amount',
        'tax_amount',
        'total_amount',
        'total_euros',
        'cost_amount',
        'margin_amount',
        'profit_amount',
        'fx_rated_at',
        'requires_billing_lines',
        'notify_technician',
        'organization_seconds',
        'completion_seconds',
        'legacy_erp_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => WorkOrderStage::class,
            'confirmed_at' => 'datetime',
            'is_urgent' => 'boolean',
            'sync_grouping' => 'boolean',
            'is_intercompany' => 'boolean',
            'notes_alert' => 'boolean',
            'internal_notes_alert' => 'boolean',
            'is_reviewed' => 'boolean',
            'received_at' => 'datetime',
            'intervention_at' => 'datetime',
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'expected_close_at' => 'datetime',
            'sla_at' => 'datetime',
            'closed_at' => 'datetime',
            'billed_at' => 'datetime',
            'sent_at' => 'datetime',
            'received_at_overridden' => 'boolean',
            'is_sla_reviewed' => 'boolean',
            'tax_rate' => 'decimal:2',
            'tax_included' => 'boolean',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_euros' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'fx_rated_at' => 'datetime',
            'requires_billing_lines' => 'boolean',
            'notify_technician' => 'boolean',
            'organization_seconds' => 'integer',
            'completion_seconds' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $workOrder): void {
            if ($workOrder->public_id === null || $workOrder->public_id === '') {
                $workOrder->public_id = (string) Str::uuid();
            }
        });

        static::saving(function (self $workOrder): void {
            $workOrder->assertStageInvariant();
        });
    }

    public function isEstimate(): bool
    {
        return $this->stage === WorkOrderStage::Estimate;
    }

    public function isConfirmedWorkOrder(): bool
    {
        return $this->stage === WorkOrderStage::WorkOrder;
    }

    private function assertStageInvariant(): void
    {
        if ($this->status_id === null) {
            throw new InvalidArgumentException('A work order requires status_id.');
        }

        $kind = $this->statusKindValue();

        if ($this->stage === WorkOrderStage::Estimate) {
            if ($kind !== WorkOrderStage::Estimate->value || $this->confirmed_at !== null) {
                throw new InvalidArgumentException(
                    'An estimate requires an estimate status and must not have confirmed_at.',
                );
            }

            return;
        }

        if ($this->stage === WorkOrderStage::WorkOrder) {
            if ($kind !== WorkOrderStage::WorkOrder->value || $this->confirmed_at === null) {
                throw new InvalidArgumentException(
                    'A work order requires a work-order status and confirmed_at.',
                );
            }
        }
    }

    private function statusKindValue(): ?string
    {
        $statusId = $this->status_id;

        if ($statusId === null) {
            return null;
        }

        $stored = WorkOrderStatus::query()->whereKey($statusId)->value('kind');

        return $stored instanceof WorkOrderStage ? $stored->value : ($stored !== null ? (string) $stored : null);
    }

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function billingCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'billing_company_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return BelongsTo<Requester, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Requester::class);
    }

    /**
     * @return BelongsTo<WorkOrderStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStatus::class, 'status_id');
    }

    /**
     * @return BelongsTo<WorkOrderType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(WorkOrderType::class, 'work_order_type_id');
    }

    /**
     * @return BelongsTo<ClientPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(ClientPriority::class, 'client_priority_id');
    }

    /**
     * @return BelongsTo<Delegation, $this>
     */
    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<ContractIteration, $this>
     */
    public function contractIteration(): BelongsTo
    {
        return $this->belongsTo(ContractIteration::class, 'contract_iteration_id');
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsTo<Evaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function sourceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_work_order_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function parentWorkOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_work_order_id');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function childWorkOrders(): HasMany
    {
        return $this->hasMany(self::class, 'parent_work_order_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function invoicingWorkOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invoicing_work_order_id');
    }

    /**
     * @return HasMany<WorkOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<WorkOrderTechnician, $this>
     */
    public function technicians(): HasMany
    {
        return $this->hasMany(WorkOrderTechnician::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_collaborators')->withTimestamps();
    }

    /**
     * @return HasMany<WorkOrderAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(WorkOrderAttachment::class)->latest();
    }

    /**
     * @return HasMany<WorkOrderChecklistCompletion, $this>
     */
    public function checklistCompletions(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistCompletion::class);
    }
}
