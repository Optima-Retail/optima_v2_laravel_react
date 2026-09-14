<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianChat extends Model
{
    use SoftDeletes;

    protected $table = 'technician_chats';

    /** @var list<string> */
    protected $fillable = [
        'company_relationship_id',
        'name',
    ];

    /** @return BelongsTo<CompanyRelationship, $this> */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class, 'company_relationship_id');
    }

    /** @return HasMany<TechnicianChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TechnicianChatMessage::class, 'chat_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function unreadUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'technician_chat_unreads', 'chat_id', 'user_id')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function mutedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'technician_chat_mutes', 'chat_id', 'user_id')->withTimestamps();
    }
}
