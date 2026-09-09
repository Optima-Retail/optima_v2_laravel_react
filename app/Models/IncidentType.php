<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncidentType extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'default_priority_id',
        'origin_selectable',
        'origin_options',
        'default_origin_type',
        'origin_required',
        'related_type',
        'show_related',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_priority_id' => 'integer',
            'origin_selectable' => 'boolean',
            'origin_options' => 'array',
            'origin_required' => 'boolean',
            'show_related' => 'boolean',
        ];
    }

    /**
     * @return array{
     *     origin_selectable: bool,
     *     origin_options: list<string>,
     *     default_origin_type: string|null,
     *     origin_required: bool,
     *     related_type: string|null,
     *     show_related: bool
     * }
     */
    public function formConfig(): array
    {
        /** @var list<string> $options */
        $options = array_values(array_filter(
            is_array($this->origin_options) ? $this->origin_options : [],
            fn ($value): bool => is_string($value) && $value !== '',
        ));

        return [
            'origin_selectable' => (bool) $this->origin_selectable,
            'origin_options' => $options,
            'default_origin_type' => $this->default_origin_type,
            'origin_required' => (bool) $this->origin_required,
            'related_type' => $this->related_type,
            'show_related' => (bool) $this->show_related,
        ];
    }

    /**
     * @return BelongsTo<IncidentPriority, $this>
     */
    public function defaultPriority(): BelongsTo
    {
        return $this->belongsTo(IncidentPriority::class, 'default_priority_id');
    }

    /**
     * @return HasMany<IncidentSubtype, $this>
     */
    public function subtypes(): HasMany
    {
        return $this->hasMany(IncidentSubtype::class);
    }
}
