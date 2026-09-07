<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DelegationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delegation extends Model
{
    /** @use HasFactory<DelegationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tax_id',
        'company_id',
        'address',
        'currency_id',
        'country_id',
        'series_id',
        'cost_includes_vat',
        'recovers_vat',
        'billing_info',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_includes_vat' => 'boolean',
            'recovers_vat' => 'boolean',
            'billing_info' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * @return HasMany<Establishment, $this>
     */
    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    public function softDeleteSafely(): bool
    {
        return (bool) $this->delete();
    }
}
