<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplateField extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'form_template_section_id',
        'sort_order',
        'type',
        'label',
        'default_value',
        'is_required',
        'is_repeatable',
        'is_visible',
        'is_locked',
        'is_cloned',
        'payload',
        'parent_id',
        'conditional_field_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'is_repeatable' => 'boolean',
            'is_visible' => 'boolean',
            'is_locked' => 'boolean',
            'is_cloned' => 'boolean',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FormTemplateSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(FormTemplateSection::class, 'form_template_section_id');
    }

    /**
     * @return BelongsTo<FormTemplateField, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
