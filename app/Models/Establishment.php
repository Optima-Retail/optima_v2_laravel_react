<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EstablishmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Establishment extends Model
{
    /** @use HasFactory<EstablishmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country_id',
        'timezone_id',
        'delegation_id',
        'is_active',
        'billing_company_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * @return BelongsTo<Company, $this>
     */
    public function billingCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'billing_company_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Timezone, $this>
     */
    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    /**
     * @return BelongsTo<Delegation, $this>
     */
    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }

    public function softDeleteSafely(): bool
    {
        if ($this->code !== null && $this->code !== '') {
            $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
            $this->code = substr($this->code, 0, max(1, 64 - strlen($suffix))).$suffix;
            $this->save();
        }

        return (bool) $this->delete();
    }
}
