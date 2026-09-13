<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityScoreLedger extends Model
{
    protected $table = 'quality_score_ledger';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'caused_by_user_id',
        'action_id',
        'previous_score',
        'new_score',
        'delta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_score' => 'float',
            'new_score' => 'float',
            'delta' => 'float',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function causedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by_user_id');
    }

    /**
     * @return BelongsTo<Action, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }
}
