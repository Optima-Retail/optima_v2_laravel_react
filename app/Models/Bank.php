<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'legal_name',
        'country_id',
        'swift_bic',
        'national_bank_code',
        'lei',
        'supervisor_code',
        'website',
        'is_active',
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
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Soft-delete the bank after releasing its unique national code.
     */
    public function softDeleteSafely(): bool
    {
        if ($this->national_bank_code !== null && $this->national_bank_code !== '') {
            $this->national_bank_code = null;
            $this->save();
        }

        return (bool) $this->delete();
    }
}
