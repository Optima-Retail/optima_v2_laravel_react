<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Config\Checklists\Enums\ChecklistDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checklist extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'requires_validation',
        'document_type',
        'work_order_status_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_validation' => 'boolean',
            'document_type' => ChecklistDocumentType::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkOrderStatus, $this>
     */
    public function workOrderStatus(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStatus::class);
    }

    /**
     * @return HasMany<WorkOrderChecklistCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistCompletion::class);
    }
}
