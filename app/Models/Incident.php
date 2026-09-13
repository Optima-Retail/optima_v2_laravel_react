<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'subject',
        'comment',
        'incident_status_id',
        'incident_priority_id',
        'incident_type_id',
        'incident_subtype_id',
        'establishment_id',
        'evaluation_id',
        'control_at',
        'closed_at',
        'duration_seconds',
        'qc_duration_seconds',
        'requester_user_id',
        'responsible_user_id',
        'qc_responsible_user_id',
        'origin_type',
        'origin_id',
        'related_type',
        'related_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'control_at' => 'datetime',
            'closed_at' => 'datetime',
            'duration_seconds' => 'integer',
            'qc_duration_seconds' => 'integer',
            'origin_id' => 'integer',
            'related_id' => 'integer',
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
     * @return BelongsTo<IncidentStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(IncidentStatus::class, 'incident_status_id');
    }

    /**
     * @return BelongsTo<IncidentPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(IncidentPriority::class, 'incident_priority_id');
    }

    /**
     * @return BelongsTo<IncidentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class, 'incident_type_id');
    }

    /**
     * @return BelongsTo<IncidentSubtype, $this>
     */
    public function subtype(): BelongsTo
    {
        return $this->belongsTo(IncidentSubtype::class, 'incident_subtype_id');
    }

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * @return BelongsTo<Evaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
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
     * @return BelongsTo<User, $this>
     */
    public function qcResponsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_responsible_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'incident_collaborators')->withTimestamps();
    }

    /**
     * @return HasMany<IncidentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(IncidentLine::class);
    }
}
