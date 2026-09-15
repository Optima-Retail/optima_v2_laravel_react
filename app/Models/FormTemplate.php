<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Forms\Enums\FormTemplateOwnerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplate extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'form_type_id',
        'language_id',
        'is_default',
        'work_order_type_id',
        'owner_type',
        'brand_id',
        'company_relationship_id',
        'establishment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_type' => FormTemplateOwnerType::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<FormType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return BelongsTo<WorkOrderType, $this>
     */
    public function workOrderType(): BelongsTo
    {
        return $this->belongsTo(WorkOrderType::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * @return HasMany<FormTemplateSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(FormTemplateSection::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Establishment, $this>
     */
    public function establishments(): BelongsToMany
    {
        return $this->belongsToMany(Establishment::class, 'establishment_form_template')
            ->withPivot(['id', 'work_order_type_id'])
            ->withTimestamps();
    }

    public function ownerLabel(): string
    {
        return match ($this->owner_type) {
            FormTemplateOwnerType::Brand => (string) ($this->brand?->name ?? ''),
            FormTemplateOwnerType::Customer => (string) (
                $this->companyRelationship?->relatedCompany?->name
                ?? $this->companyRelationship?->relatedCompany?->tradename
                ?? ''
            ),
            FormTemplateOwnerType::Establishment => (string) ($this->establishment?->name ?? ''),
            FormTemplateOwnerType::Bible => FormTemplateOwnerType::Bible->label(),
            default => '',
        };
    }
}
