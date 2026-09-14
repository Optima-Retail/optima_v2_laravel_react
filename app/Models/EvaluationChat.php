<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvaluationChat extends Model
{
    use SoftDeletes;

    protected $table = 'evaluation_chats';

    /** @var list<string> */
    protected $fillable = [
        'evaluation_id',
        'name',
    ];

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

    /** @return HasMany<EvaluationChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(EvaluationChatMessage::class, 'chat_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function unreadUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evaluation_chat_unreads', 'chat_id', 'user_id')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function mutedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evaluation_chat_mutes', 'chat_id', 'user_id')->withTimestamps();
    }
}
