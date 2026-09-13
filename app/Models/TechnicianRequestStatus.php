<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianRequestStatus extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'name',
        'color',
        'lifecycle',
        'is_open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TechnicianRequestStatusKind::class,
            'lifecycle' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TechnicianRequest, $this>
     */
    public function technicianRequests(): HasMany
    {
        return $this->hasMany(TechnicianRequest::class, 'technician_request_status_id');
    }
}
