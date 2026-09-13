<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderTechnician extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_order_id',
        'company_relationship_id',
        'is_selected',
        'description',
        'status_id',
        'attendance_confirmation_type_id',
        'quote_net_amount',
        'quote_tax_amount',
        'quote_total_amount',
        'quote_total_euros',
        'tax_rate',
        'tax_included',
        'currency_id',
        'delegation_id',
        'quote_document_path',
        'public_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'quote_net_amount' => 'decimal:2',
            'quote_tax_amount' => 'decimal:2',
            'quote_total_amount' => 'decimal:2',
            'quote_total_euros' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_included' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class, 'company_relationship_id');
    }

    /**
     * @return BelongsTo<WorkOrderTechnicianStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkOrderTechnicianStatus::class, 'status_id');
    }

    /**
     * @return BelongsTo<TechnicianAttendanceConfirmationType, $this>
     */
    public function attendanceConfirmationType(): BelongsTo
    {
        return $this->belongsTo(TechnicianAttendanceConfirmationType::class, 'attendance_confirmation_type_id');
    }
}
