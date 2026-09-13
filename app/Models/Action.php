<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Action extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'weight_key',
    ];

    /**
     * @return HasMany<KpiConfiguration, $this>
     */
    public function kpiConfigurations(): HasMany
    {
        return $this->hasMany(KpiConfiguration::class);
    }

    /**
     * @return HasMany<UserActionScore, $this>
     */
    public function userActionScores(): HasMany
    {
        return $this->hasMany(UserActionScore::class);
    }
}
