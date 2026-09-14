<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Companies\Enums\TechnicianRatingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianRating extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_relationship_id',
        'work_order_id',
        'score',
        'notes',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'source' => TechnicianRatingSource::class,
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
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
