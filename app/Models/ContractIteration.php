<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Contracts\Enums\ContractIterationPeriodicity;
use App\Domain\Contracts\Enums\ContractIterationPeriodicityKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractIteration extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'contract_id',
        'work_order_type_id',
        'starts_on',
        'ends_on',
        'periodicity',
        'periodicity_kind',
        'interval',
        'weekdays',
        'month_days',
        'months',
        'cost_amount',
        'subject',
        'establishment_ids',
        'form_template_id',
        'invoicing_aggregation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'periodicity' => ContractIterationPeriodicity::class,
            'periodicity_kind' => ContractIterationPeriodicityKind::class,
            'interval' => 'integer',
            'weekdays' => 'array',
            'month_days' => 'array',
            'months' => 'array',
            'cost_amount' => 'decimal:2',
            'establishment_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<WorkOrderType, $this>
     */
    public function workOrderType(): BelongsTo
    {
        return $this->belongsTo(WorkOrderType::class);
    }

    /**
     * @return BelongsTo<FormTemplate, $this>
     */
    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    /**
     * @return BelongsTo<ContractInvoicingAggregation, $this>
     */
    public function invoicingAggregation(): BelongsTo
    {
        return $this->belongsTo(ContractInvoicingAggregation::class, 'invoicing_aggregation_id');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'contract_iteration_id');
    }
}
