<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Domain\Companies\Enums\WorkOrderGroupingBasis;
use Database\Factories\CompanyRelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'optima_score_count' => 0,
        'customer_score_count' => 0,
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
        'delegation_id',
        'billing_language_id',
        'series_id',
        'integration_id',
        'integration_external_id',
        'reported_customer_relationship_id',
        'corrective_work_order_owner_id',
        'preventive_work_order_owner_id',
        'quality_owner_id',
        'account_owner_id',
        'commercial_owner_id',
        'sourced_by_user_id',
        'internal_notes',
        'notes_alert',
        'internal_notes_alert',
        'onboarding_notes',
        'billing_comments',
        'rates_notes',
        'archetype',
        'tax_rate',
        'is_reviewed',
        'is_email_reviewed',
        'is_invoicing_reviewed',
        'invoicing_reviewed_at',
        'quote_close_days',
        'recurring_meeting_frequency',
        'sales_feedback_meeting_frequency',
        'group_zero_cost_work_orders',
        'load_materials_on_corrective',
        'group_preventive_and_corrective',
        'group_preventives_by',
        'group_correctives_by',
        'invoice_at_month_end',
        'requires_purchase_order',
        'requires_requester',
        'is_franchise',
        'requires_justification',
        'auto_send_invoices',
        'send_invoices_individually',
        'send_debt_reminders',
        'is_quality_control_contactable',
        'requires_client_informed_check',
        'requires_intervention_scheduled_check',
        'requires_budget_approval_limit',
        'is_intercompany',
        'optima_score',
        'customer_score',
        'average_score',
        'optima_score_count',
        'customer_score_count',
        'has_health_and_safety',
        'is_field_technician',
        'is_creditor',
        'is_vip',
        'is_available_24h',
        'day_start_at',
        'day_end_at',
        'has_garnishment',
        'whatsapp_messaging_authorized',
        'registered_at',
        'legacy_status_id',
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
            'group_preventives_by' => WorkOrderGroupingBasis::class,
            'group_correctives_by' => WorkOrderGroupingBasis::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'invoicing_reviewed_at' => 'datetime',
            'registered_at' => 'datetime',
            'tax_rate' => 'decimal:2',
            'optima_score' => 'decimal:2',
            'customer_score' => 'decimal:2',
            'average_score' => 'decimal:2',
            'notes_alert' => 'boolean',
            'internal_notes_alert' => 'boolean',
            'is_reviewed' => 'boolean',
            'is_email_reviewed' => 'boolean',
            'is_invoicing_reviewed' => 'boolean',
            'group_zero_cost_work_orders' => 'boolean',
            'load_materials_on_corrective' => 'boolean',
            'group_preventive_and_corrective' => 'boolean',
            'invoice_at_month_end' => 'boolean',
            'requires_purchase_order' => 'boolean',
            'requires_requester' => 'boolean',
            'is_franchise' => 'boolean',
            'requires_justification' => 'boolean',
            'auto_send_invoices' => 'boolean',
            'send_invoices_individually' => 'boolean',
            'send_debt_reminders' => 'boolean',
            'is_quality_control_contactable' => 'boolean',
            'requires_client_informed_check' => 'boolean',
            'requires_intervention_scheduled_check' => 'boolean',
            'requires_budget_approval_limit' => 'boolean',
            'is_intercompany' => 'boolean',
            'has_health_and_safety' => 'boolean',
            'is_field_technician' => 'boolean',
            'is_creditor' => 'boolean',
            'is_vip' => 'boolean',
            'is_available_24h' => 'boolean',
            'has_garnishment' => 'boolean',
            'whatsapp_messaging_authorized' => 'boolean',
            'quote_close_days' => 'integer',
            'recurring_meeting_frequency' => 'integer',
            'sales_feedback_meeting_frequency' => 'integer',
            'optima_score_count' => 'integer',
            'customer_score_count' => 'integer',
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

    /**
     * @return BelongsTo<Delegation, $this>
     */
    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function billingLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'billing_language_id');
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function reportedCustomerRelationship(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reported_customer_relationship_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function correctiveWorkOrderOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrective_work_order_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preventiveWorkOrderOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preventive_work_order_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function qualityOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function commercialOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sourcedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sourced_by_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_relationship_collaborators')->withTimestamps();
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function softDeleteSafely(): bool
    {
        $this->deleted_token = $this->getKey().'_'.now()->timestamp;
        $this->save();

        return (bool) $this->delete();
    }
}
