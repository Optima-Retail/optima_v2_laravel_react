<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FieldHelp extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'context',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<FieldHelpTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(FieldHelpTranslation::class);
    }

    /**
     * @return HasOne<FieldHelpTranslation, $this>
     */
    public function translation(): HasOne
    {
        return $this->hasOne(FieldHelpTranslation::class);
    }
}
