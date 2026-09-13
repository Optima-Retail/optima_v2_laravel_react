<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\TechnicianRequests\Enums\TechnicianRequestPriorityKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianRequestPriority extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'key',
        'color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => TechnicianRequestPriorityKey::class,
        ];
    }

    /**
     * @return HasMany<TechnicianRequest, $this>
     */
    public function technicianRequests(): HasMany
    {
        return $this->hasMany(TechnicianRequest::class, 'technician_request_priority_id');
    }
}
