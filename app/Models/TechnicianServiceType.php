<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianServiceType extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_relationship_id',
        'service_type_id',
    ];

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
