<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandMessage extends Model
{
    use SoftDeletes;

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
        'brand_id',
        'user_id',
        'body',
        'type',
        'attachment_path',
        'attachment_name',
    ];

    public function isAttachment(): bool
    {
        return in_array($this->type, self::ATTACHMENT_TYPES, true);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
