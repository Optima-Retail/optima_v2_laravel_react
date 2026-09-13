<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiConfiguration extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'action_id',
        'min_value',
        'max_value',
        'percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_value' => 'float',
            'max_value' => 'float',
            'percentage' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Action, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }
}
