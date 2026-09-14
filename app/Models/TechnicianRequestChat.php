<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianRequestChat extends Model
{
    use SoftDeletes;

    protected $table = 'technician_request_chats';

    /** @var list<string> */
    protected $fillable = [
        'technician_request_id',
        'name',
    ];

    /** @return BelongsTo<TechnicianRequest, $this> */
    public function technicianRequest(): BelongsTo
    {
        return $this->belongsTo(TechnicianRequest::class, 'technician_request_id');
    }

    /** @return HasMany<TechnicianRequestChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TechnicianRequestChatMessage::class, 'chat_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function unreadUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'technician_request_chat_unreads', 'chat_id', 'user_id')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function mutedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'technician_request_chat_mutes', 'chat_id', 'user_id')->withTimestamps();
    }
}
