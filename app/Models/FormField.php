<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormField extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'form_section_id',
        'sort_order',
        'type',
        'label',
        'value',
        'placeholder',
        'is_required',
        'is_repeatable',
        'is_visible',
        'is_locked',
        'is_cloned',
        'payload',
        'task_to_perform_id',
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
     * @return BelongsTo<FormSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }

    /**
     * @return BelongsTo<TaskToPerform, $this>
     */
    public function taskToPerform(): BelongsTo
    {
        return $this->belongsTo(TaskToPerform::class);
    }
}
