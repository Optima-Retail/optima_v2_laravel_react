<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianRate extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_relationship_id',
        'labor_weekday_amount',
        'labor_night_amount',
        'labor_weekend_amount',
        'labor_holiday_amount',
        'labor_urgent_amount',
        'travel_weekday_amount',
        'travel_night_amount',
        'travel_weekend_amount',
        'travel_holiday_amount',
        'travel_urgent_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'labor_weekday_amount' => 'decimal:2',
            'labor_night_amount' => 'decimal:2',
            'labor_weekend_amount' => 'decimal:2',
            'labor_holiday_amount' => 'decimal:2',
            'labor_urgent_amount' => 'decimal:2',
            'travel_weekday_amount' => 'decimal:2',
            'travel_night_amount' => 'decimal:2',
            'travel_weekend_amount' => 'decimal:2',
            'travel_holiday_amount' => 'decimal:2',
            'travel_urgent_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }
}
