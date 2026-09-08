<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EstablishmentType extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'health_and_safety_delay_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'health_and_safety_delay_days' => 'integer',
        ];
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
        $this->code = $this->uniqueSoftDeletedCode($this->code);
        $this->save();

        return (bool) $this->delete();
    }

    private function uniqueSoftDeletedCode(string $code): string
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $max = 64;
        $baseMax = max(1, $max - strlen($suffix));

        return substr($code, 0, $baseMax).$suffix;
    }
}
