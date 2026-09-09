<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluation extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject',
        'public_id',
        'establishment_id',
        'evaluation_status_id',
        'responsible_user_id',
        'next_action_at',
        'closed_at',
        'facility_question',
        'technician_question',
        'visit_count',
        'qc_duration_minutes',
        'call_count',
        'first_contact_attempt_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_action_at' => 'datetime',
            'closed_at' => 'datetime',
            'first_contact_attempt_at' => 'datetime',
            'visit_count' => 'integer',
            'qc_duration_minutes' => 'integer',
            'call_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * @return BelongsTo<EvaluationStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatus::class, 'evaluation_status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
