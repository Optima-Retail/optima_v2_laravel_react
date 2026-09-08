<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldHelpTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'field_help_id',
        'locale',
        'title',
        'description',
        'example',
    ];

    /**
     * @return BelongsTo<FieldHelp, $this>
     */
    public function fieldHelp(): BelongsTo
    {
        return $this->belongsTo(FieldHelp::class);
    }
}
