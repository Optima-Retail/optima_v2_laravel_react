<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianIncidentStatus extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'lifecycle',
        'is_open',
        'is_default',
        'marks_verified',
        'sets_response_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle' => 'integer',
            'is_open' => 'boolean',
            'is_default' => 'boolean',
            'marks_verified' => 'boolean',
            'sets_response_date' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMarksVerified(Builder $query): Builder
    {
        return $query->where('marks_verified', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSetsResponseDate(Builder $query): Builder
    {
        return $query->where('sets_response_date', true);
    }
}
