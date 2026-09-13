<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
        ];
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
}
