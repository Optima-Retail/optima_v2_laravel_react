<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EstablishmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Establishment extends Model
{
    /** @use HasFactory<EstablishmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'store_code',
        'alternate_store_code',
        'phone',
        'email',
        'emails',
        'recipient_emails',
        'address_line_1',
        'address_line_2',
        'city',
        'province_id',
        'postal_code',
        'country_id',
        'timezone_id',
        'language_id',
        'establishment_type_id',
        'delegation_id',
        'series_id',
        'billing_company_id',
        'responsible_user_id',
        'is_active',
        'is_client_priority',
        'is_reviewed',
        'is_email_reviewed',
        'has_site_health_and_safety',
        'has_customer_health_and_safety',
        'is_quality_control_contactable',
        'has_parking',
        'is_ulez_zone',
        'latitude',
        'longitude',
        'tax_rate',
        'tax_included',
        'legacy_erp_id',
        'integration_external_id',
        'notes',
        'notes_alert',
        'internal_notes',
        'internal_notes_alert',
        'voicebot_time_slots',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_client_priority' => 'boolean',
            'is_reviewed' => 'boolean',
            'is_email_reviewed' => 'boolean',
            'has_site_health_and_safety' => 'boolean',
            'has_customer_health_and_safety' => 'boolean',
            'is_quality_control_contactable' => 'boolean',
            'has_parking' => 'boolean',
            'is_ulez_zone' => 'boolean',
            'tax_included' => 'boolean',
            'notes_alert' => 'boolean',
            'internal_notes_alert' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'tax_rate' => 'decimal:2',
            'voicebot_time_slots' => 'array',
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
     * @return BelongsTo<Company, $this>
     */
    public function billingCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'billing_company_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<Timezone, $this>
     */
    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return BelongsTo<EstablishmentType, $this>
     */
    public function establishmentType(): BelongsTo
    {
        return $this->belongsTo(EstablishmentType::class);
    }

    /**
     * @return BelongsTo<Delegation, $this>
     */
    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'establishment_collaborators')->withTimestamps();
    }

    /**
     * @return HasMany<Evaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function softDeleteSafely(): bool
    {
        if ($this->code !== null && $this->code !== '') {
            $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
            $this->code = substr($this->code, 0, max(1, 64 - strlen($suffix))).$suffix;
            $this->save();
        }

        return (bool) $this->delete();
    }
}
