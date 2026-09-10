<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand',
        'model',
        'license_plate',
        'company_relationship_id',
    ];

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }
}
