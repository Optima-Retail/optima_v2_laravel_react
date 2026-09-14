<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderChat extends Model
{
    use SoftDeletes;

    protected $table = 'work_order_chats';

    /** @var list<string> */
    protected $fillable = [
        'work_order_id',
        'name',
    ];

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    /** @return HasMany<WorkOrderChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(WorkOrderChatMessage::class, 'chat_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function unreadUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_chat_unreads', 'chat_id', 'user_id')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function mutedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_chat_mutes', 'chat_id', 'user_id')->withTimestamps();
    }
}
