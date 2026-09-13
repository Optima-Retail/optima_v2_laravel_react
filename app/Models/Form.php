<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Forms\Enums\FormSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'name',
        'form_type_id',
        'form_status_id',
        'language_id',
        'user_id',
        'form_template_id',
        'subject_type',
        'work_order_id',
        'company_relationship_id',
        'occurred_on',
        'app_platform_id',
        'technician_code',
        'was_edited',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => FormSubjectType::class,
            'occurred_on' => 'date',
            'app_platform_id' => 'integer',
            'was_edited' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FormType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_id');
    }

    /**
     * @return BelongsTo<FormStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(FormStatus::class, 'form_status_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FormTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }

    /**
     * @return HasMany<FormSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('sort_order');
    }

    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            FormSubjectType::WorkOrder => (string) ($this->workOrder?->code ?? $this->workOrder?->subject ?? ''),
            FormSubjectType::Technician => (string) (
                $this->companyRelationship?->relatedCompany?->name
                ?? $this->companyRelationship?->relatedCompany?->tradename
                ?? ''
            ),
            default => '',
        };
    }
}
