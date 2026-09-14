<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncidentChat extends Model
{
    use SoftDeletes;

    protected $table = 'incident_chats';

    /** @var list<string> */
    protected $fillable = [
        'incident_id',
        'name',
    ];

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    /** @return HasMany<IncidentChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(IncidentChatMessage::class, 'chat_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function unreadUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'incident_chat_unreads', 'chat_id', 'user_id')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function mutedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'incident_chat_mutes', 'chat_id', 'user_id')->withTimestamps();
    }
}
