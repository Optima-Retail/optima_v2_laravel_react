<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Compliments\Enums\ComplimentSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compliment extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_type',
        'brand_id',
        'company_relationship_id',
        'establishment_id',
        'compliment_type_id',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => ComplimentSubjectType::class,
        ];
    }

    /**
     * @return BelongsTo<ComplimentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ComplimentType::class, 'compliment_type_id');
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
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'compliment_user')
            ->withPivot('score')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ComplimentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ComplimentAttachment::class);
    }

    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            ComplimentSubjectType::Brand => (string) ($this->brand?->name ?? ''),
            ComplimentSubjectType::Customer => (string) (
                $this->companyRelationship?->relatedCompany?->name
                ?? $this->companyRelationship?->relatedCompany?->tradename
                ?? ''
            ),
            ComplimentSubjectType::Establishment => (string) ($this->establishment?->name ?? ''),
            default => '',
        };
    }
}
