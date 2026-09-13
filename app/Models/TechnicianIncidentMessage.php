<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianIncidentMessage extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_PDF = 'pdf';

    public const TYPE_WORD = 'word';

    public const TYPE_FILE = 'file';

    /**
     * @var list<string>
     */
    public const ATTACHMENT_TYPES = [
        self::TYPE_IMAGE,
        self::TYPE_PDF,
        self::TYPE_WORD,
        self::TYPE_FILE,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'technician_incident_id',
        'user_id',
        'body',
        'type',
        'attachment_path',
        'attachment_name',
    ];

    public function isAttachment(): bool
    {
        return in_array((string) $this->type, self::ATTACHMENT_TYPES, true);
    }

    /**
     * @return BelongsTo<TechnicianIncident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(TechnicianIncident::class, 'technician_incident_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
