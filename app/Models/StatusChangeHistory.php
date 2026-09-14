<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusChangeHistory extends Model
{
    protected $fillable = [
        'document_type',
        'document_id',
        'old_status_id',
        'new_status_id',
        'user_id',
        'justification',
        'price_decrease_amount',
    ];

    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'old_status_id' => 'integer',
            'new_status_id' => 'integer',
            'price_decrease_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
