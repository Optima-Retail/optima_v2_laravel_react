<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianRequest extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'is_screening',
        'parent_technician_request_id',
        'work_order_id',
        'code',
        'description',
        'notes',
        'internal_notes',
        'city',
        'postal_code',
        'address_line',
        'province_name',
        'country_id',
        'language_id',
        'requester_user_id',
        'responsible_user_id',
        'resolved_at',
        'due_at',
        'next_action_at',
        'technician_request_priority_id',
        'technician_request_status_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_screening' => 'boolean',
            'resolved_at' => 'datetime',
            'due_at' => 'datetime',
            'next_action_at' => 'datetime',
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
     * @return BelongsTo<TechnicianRequest, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_technician_request_id');
    }

    /**
     * @return HasMany<TechnicianRequest, $this>
     */
    public function screenings(): HasMany
    {
        return $this->hasMany(self::class, 'parent_technician_request_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
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
    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return BelongsTo<TechnicianRequestPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(TechnicianRequestPriority::class, 'technician_request_priority_id');
    }

    /**
     * @return BelongsTo<TechnicianRequestStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TechnicianRequestStatus::class, 'technician_request_status_id');
    }

    /**
     * @return BelongsToMany<ServiceType, $this>
     */
    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceType::class,
            'technician_request_service_type',
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<CompanyRelationship, $this>
     */
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(
            CompanyRelationship::class,
            'technician_request_technician',
            'technician_request_id',
            'company_relationship_id',
        )->withTimestamps();
    }
}
