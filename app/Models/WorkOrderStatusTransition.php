<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderStatusTransition extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'from_status_id',
        'to_status_id',
        'requires_confirmation',
        'requires_justification',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_confirmation' => 'boolean',
            'requires_justification' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<WorkOrderStatus, $this>
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStatus::class, 'from_status_id');
    }

    /**
     * @return BelongsTo<WorkOrderStatus, $this>
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStatus::class, 'to_status_id');
    }
}
