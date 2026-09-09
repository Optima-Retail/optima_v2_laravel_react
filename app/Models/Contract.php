<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'company_id',
        'responsible_user_id',
        'contract_status_id',
        'language_id',
        'description',
        'work_order_subject',
        'progress',
        'total_progress',
        'total_amount',
        'signed_at',
        'canceled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'total_progress' => 'integer',
            'total_amount' => 'decimal:2',
            'signed_at' => 'datetime',
            'canceled_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return BelongsTo<ContractStatus, $this>
     */
    public function contractStatus(): BelongsTo
    {
        return $this->belongsTo(ContractStatus::class);
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return BelongsToMany<Establishment, $this>
     */
    public function establishments(): BelongsToMany
    {
        return $this->belongsToMany(Establishment::class, 'contract_establishment');
    }

    /**
     * @return HasMany<ContractAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class)->latest();
    }
}
