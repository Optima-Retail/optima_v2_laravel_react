<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianIncident extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status_id',
        'technician_incident_type_id',
        'incident_text',
        'response_text',
        'requested_by_id',
        'responded_by_id',
        'responded_at',
        'technician_id',
        'is_verified',
        'verified_at',
        'verified_by_id',
        'due_at',
        'negotiation_succeeded',
        'unsuccessful_negotiation_solution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'date',
            'verified_at' => 'datetime',
            'due_at' => 'datetime',
            'is_verified' => 'boolean',
            'negotiation_succeeded' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TechnicianIncidentStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TechnicianIncidentStatus::class, 'status_id');
    }

    /**
     * @return BelongsTo<TechnicianIncidentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TechnicianIncidentType::class, 'technician_incident_type_id');
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class, 'technician_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /**
     * @return HasMany<TechnicianIncidentMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TechnicianIncidentMessage::class);
    }
}
