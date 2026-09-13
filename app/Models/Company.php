<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Enums\PersonType;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tradename',
        'slug',
        'tax_id',
        'kind',
        'country_id',
        'residence_country_id',
        'person_type',
        'email',
        'phone',
        'website',
        'address_line_1',
        'address_line_2',
        'city',
        'province_id',
        'postal_code',
        'employee_count',
        'is_active',
        'logo',
        'brand_id',
        'language_id',
        'latitude',
        'longitude',
        'legacy_erp_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CompanyKind::class,
            'person_type' => PersonType::class,
            'is_active' => 'boolean',
            'employee_count' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            $company->slug = $company->slug !== null && $company->slug !== ''
                ? Str::slug($company->slug)
                : static::uniqueSlugFromName($company->name);
        });
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function residenceCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'residence_country_id');
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot(['id', 'is_active'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<CompanyUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    /**
     * @return HasMany<CompanyRelationship, $this>
     */
    public function ownedRelationships(): HasMany
    {
        return $this->hasMany(CompanyRelationship::class, 'owner_company_id');
    }

    /**
     * @return HasMany<CompanyRelationship, $this>
     */
    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(CompanyRelationship::class, 'related_company_id');
    }

    /**
     * Client priorities available for this company when it acts as a client (legacy clientes_prioridades).
     *
     * @return BelongsToMany<ClientPriority, $this>
     */
    public function priorities(): BelongsToMany
    {
        return $this->belongsToMany(ClientPriority::class, 'company_priority', 'company_id', 'client_priority_id')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<Establishment, $this>
     */
    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    /**
     * Weekly opening hours (legacy horarios).
     *
     * @return HasOne<CompanySchedule, $this>
     */
    public function schedule(): HasOne
    {
        return $this->hasOne(CompanySchedule::class);
    }

    /**
     * @return HasMany<Delegation, $this>
     */
    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }

    /**
     * @return HasMany<Brand, $this>
     */
    public function corporationBrands(): HasMany
    {
        return $this->hasMany(Brand::class, 'corporation_company_id');
    }

    /**
     * @return HasMany<NumberingPattern, $this>
     */
    public function numberingPatterns(): HasMany
    {
        return $this->hasMany(NumberingPattern::class);
    }

    /**
     * @return HasMany<Requester, $this>
     */
    public function requesters(): HasMany
    {
        return $this->hasMany(Requester::class);
    }

    public function softDeleteSafely(): bool
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $this->slug = substr((string) $this->slug, 0, max(1, 64 - strlen($suffix))).$suffix;

        if ($this->tax_id !== null && $this->tax_id !== '') {
            $this->tax_id = substr($this->tax_id, 0, max(1, 32 - strlen($suffix))).$suffix;
        }

        $this->save();

        return (bool) $this->delete();
    }

    public static function uniqueSlugFromName(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = substr($base, 0, 60).'-'.$i;
            $i++;
        }

        return $slug;
    }
}
