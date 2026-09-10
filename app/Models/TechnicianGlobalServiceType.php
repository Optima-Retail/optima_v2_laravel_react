<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianGlobalServiceType extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_relationship_id',
        'global_service_type_id',
    ];

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }

    /**
     * @return BelongsTo<GlobalServiceType, $this>
     */
    public function globalServiceType(): BelongsTo
    {
        return $this->belongsTo(GlobalServiceType::class);
    }
}
