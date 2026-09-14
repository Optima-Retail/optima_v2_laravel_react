<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianRequestChatMessageAttachment extends Model
{
    use SoftDeletes;

    protected $table = 'technician_request_chat_message_attachments';

    /** @var list<string> */
    protected $fillable = [
        'message_id',
        'name',
        'path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    /** @return BelongsTo<TechnicianRequestChatMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TechnicianRequestChatMessage::class, 'message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
