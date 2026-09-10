<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderTypeServiceType extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_order_type_id',
        'service_type_id',
    ];

    /**
     * @return BelongsTo<WorkOrderType, $this>
     */
    public function workOrderType(): BelongsTo
    {
        return $this->belongsTo(WorkOrderType::class);
    }

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
