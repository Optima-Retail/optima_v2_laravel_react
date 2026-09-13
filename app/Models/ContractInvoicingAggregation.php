<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Contracts\Enums\ContractInvoicingAggregationFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractInvoicingAggregation extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contract_id',
        'subject',
        'billing_frequency',
        'billing_day',
        'billing_cycle_start',
        'per_establishment',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'billing_frequency' => 'monthly',
        'per_establishment' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_frequency' => ContractInvoicingAggregationFrequency::class,
            'billing_day' => 'integer',
            'billing_cycle_start' => 'date',
            'per_establishment' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return HasMany<ContractIteration, $this>
     */
    public function iterations(): HasMany
    {
        return $this->hasMany(ContractIteration::class, 'invoicing_aggregation_id');
    }
}
