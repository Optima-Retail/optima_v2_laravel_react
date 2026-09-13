<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\QualityScores\Enums\QualityScoreDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActionScore extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'weight',
        'max_value',
        'value',
        'user_id',
        'action_id',
        'document_type',
        'document_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'max_value' => 'float',
            'value' => 'float',
            'document_type' => QualityScoreDocumentType::class,
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
     * @return BelongsTo<Action, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }
}
