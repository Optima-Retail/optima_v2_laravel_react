<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanySchedule extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'monday_opens',
        'monday_closes',
        'tuesday_opens',
        'tuesday_closes',
        'wednesday_opens',
        'wednesday_closes',
        'thursday_opens',
        'thursday_closes',
        'friday_opens',
        'friday_closes',
        'saturday_opens',
        'saturday_closes',
        'sunday_opens',
        'sunday_closes',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
