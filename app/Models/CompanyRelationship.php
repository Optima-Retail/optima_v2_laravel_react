<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use Database\Factories\CompanyRelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyRelationship extends Model
{
    /** @use HasFactory<CompanyRelationshipFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'deleted_token' => '',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_company_id',
        'related_company_id',
        'kind',
        'status',
        'classification',
        'owner_reference',
        'related_reference',
        'brand_id',
        'external_code',
        'notes',
        'starts_at',
        'ends_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CompanyRelationshipKind::class,
            'status' => CompanyRelationshipStatus::class,
            'classification' => CompanyRelationshipClassification::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function ownerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function relatedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'related_company_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function softDeleteSafely(): bool
    {
        $this->deleted_token = $this->getKey().'_'.now()->timestamp;
        $this->save();

        return (bool) $this->delete();
    }
}
