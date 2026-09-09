<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NumberingPattern extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'resource',
        'segments',
        'reset_yearly',
        'last_sequence',
        'last_year',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'reset_yearly' => 'boolean',
            'is_active' => 'boolean',
            'last_sequence' => 'integer',
            'last_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function softDeleteSafely(): bool
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $this->resource = substr($this->resource, 0, max(1, 64 - strlen($suffix))).$suffix;
        $this->save();

        return (bool) $this->delete();
    }
}
