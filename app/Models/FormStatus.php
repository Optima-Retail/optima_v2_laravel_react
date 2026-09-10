<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormStatus extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'next_status_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_status_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FormStatus, $this>
     */
    public function nextStatus(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_status_id');
    }
}
