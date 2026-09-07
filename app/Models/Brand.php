<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'account_manager_id',
        'commercial_manager_id',
        'loyalty_meeting_frequency',
        'is_quality_control_contactable',
        'send_debt_reminders',
        'corporation_company_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_quality_control_contactable' => 'boolean',
            'send_debt_reminders' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function commercialManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_manager_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'corporation_company_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_collaborators')->withTimestamps();
    }

    /**
     * @return HasMany<BrandMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BrandMessage::class)->latest();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function softDeleteSafely(): bool
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $this->name = substr($this->name, 0, max(1, 255 - strlen($suffix))).$suffix;
        $this->save();

        return (bool) $this->delete();
    }
}
