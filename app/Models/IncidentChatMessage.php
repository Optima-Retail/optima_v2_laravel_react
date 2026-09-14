<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncidentChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'incident_chat_messages';

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

    /** @return BelongsTo<IncidentChat, $this> */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(IncidentChat::class, 'chat_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<IncidentChatMessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentChatMessageAttachment::class, 'message_id');
    }
}
