<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row for legacy `establecimiento_plantilla`.
 */
class EstablishmentFormTemplate extends Model
{
    protected $table = 'establishment_form_template';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'establishment_id',
        'form_template_id',
        'work_order_type_id',
    ];

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * @return BelongsTo<FormTemplate, $this>
     */
    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    /**
     * @return BelongsTo<WorkOrderType, $this>
     */
    public function workOrderType(): BelongsTo
    {
        return $this->belongsTo(WorkOrderType::class);
    }
}
