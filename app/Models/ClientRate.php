<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientRate extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_relationship_id',
        'client_priority_id',
        'work_order_type_id',
        'travel_amount',
        'extra_travel_amount',
        'labor_amount',
        'extra_labor_amount',
        'due_hours',
        'sla_hours',
        'is_urgent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'travel_amount' => 'decimal:2',
            'extra_travel_amount' => 'decimal:2',
            'labor_amount' => 'decimal:2',
            'extra_labor_amount' => 'decimal:2',
            'due_hours' => 'integer',
            'sla_hours' => 'integer',
            'is_urgent' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }

    /**
     * @return BelongsTo<ClientPriority, $this>
     */
    public function clientPriority(): BelongsTo
    {
        return $this->belongsTo(ClientPriority::class);
    }

    /**
     * @return BelongsTo<WorkOrderType, $this>
     */
    public function workOrderType(): BelongsTo
    {
        return $this->belongsTo(WorkOrderType::class);
    }
}
