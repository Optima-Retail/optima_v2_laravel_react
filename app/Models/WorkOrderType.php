<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderType extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'color',
    ];

    /**
     * @return HasMany<WorkOrderTypeServiceType, $this>
     */
    public function workOrderTypeServiceTypes(): HasMany
    {
        return $this->hasMany(WorkOrderTypeServiceType::class);
    }

    /**
     * @return BelongsToMany<ServiceType, $this>
     */
    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceType::class,
            'work_order_type_service_types',
            'work_order_type_id',
            'service_type_id',
        )->withTimestamps();
    }

    /**
     * Soft-delete the type after releasing its unique code.
     */
    public function softDeleteSafely(): bool
    {
        if ($this->code !== null && $this->code !== '') {
            $this->code = $this->uniqueSoftDeletedCode($this->code);
            $this->save();
        }

        return (bool) $this->delete();
    }

    private function uniqueSoftDeletedCode(string $code): string
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $max = 255;
        $baseMax = max(1, $max - strlen($suffix));

        return substr($code, 0, $baseMax).$suffix;
    }
}
