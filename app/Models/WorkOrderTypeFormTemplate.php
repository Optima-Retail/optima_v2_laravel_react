<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Forms\Enums\FormTemplateOwnerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderTypeFormTemplate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_type',
        'brand_id',
        'company_relationship_id',
        'establishment_id',
        'form_bible_id',
        'work_order_type_id',
        'form_template_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_type' => FormTemplateOwnerType::class,
        ];
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
}
