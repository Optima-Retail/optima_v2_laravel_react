<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvaluationChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'evaluation_chat_messages';

    /** @var list<string> */
    protected $fillable = [
        'chat_id',
        'user_id',
        'body',
        'type',
        'is_private',
        'arguments',
        'translatable_arguments',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'arguments' => 'array',
            'translatable_arguments' => 'array',
        ];
    }

    /** @return BelongsTo<EvaluationChat, $this> */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(EvaluationChat::class, 'chat_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EvaluationChatMessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EvaluationChatMessageAttachment::class, 'message_id');
    }
}
