<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderStatus extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'kind',
        'color',
        'lifecycle',
        'is_open',
        'is_default',
        'confirms_estimate',
        'rejects_to_estimate',
        'is_post_confirm_default',
        'sets_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => WorkOrderStage::class,
            'lifecycle' => 'integer',
            'is_open' => 'boolean',
            'is_default' => 'boolean',
            'confirms_estimate' => 'boolean',
            'rejects_to_estimate' => 'boolean',
            'is_post_confirm_default' => 'boolean',
            'sets_sent_at' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WorkOrderStatusTransition, $this>
     */
    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkOrderStatusTransition::class, 'from_status_id');
    }

    /**
     * @return HasMany<WorkOrderStatusTransition, $this>
     */
    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkOrderStatusTransition::class, 'to_status_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeKind(Builder $query, WorkOrderStage|string $kind): Builder
    {
        $value = $kind instanceof WorkOrderStage ? $kind->value : $kind;

        return $query->where('kind', $value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
