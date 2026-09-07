<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'username',
    'password',
    'locale',
    'manager_id',
    'team_leader_id',
    'team_id',
    'timezone_id',
    'brand_id',
    'phone',
    'telephony_phone_number',
    'pbx_extension',
    'telegram_user_id',
    'external_hr_id',
    'is_active',
    'is_internal_employee',
    'is_team_account',
    'is_preventive_specialist',
    'performance_factor',
    'invoiced_revenue_target',
    'quality_score',
    'balance',
    'budget_approval_limit',
    'sso_only',
    'must_change_password',
    'active_company_id',
])]
#[Hidden(['password', 'remember_token', 'totp_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(self::class, 'team_leader_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Timezone, $this>
     */
    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function activeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'active_company_id');
    }

    /**
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot(['id', 'is_active'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function belongsToCompany(int $companyId): bool
    {
        return $this->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Soft-delete the user after releasing the unique email.
     */
    public function softDeleteSafely(): bool
    {
        $this->email = $this->uniqueSoftDeletedEmail($this->email);
        if ($this->username !== null) {
            $this->username = $this->uniqueSoftDeletedEmail($this->username);
        }
        $this->save();

        return (bool) $this->delete();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_internal_employee' => 'boolean',
            'is_team_account' => 'boolean',
            'is_preventive_specialist' => 'boolean',
            'performance_factor' => 'decimal:2',
            'invoiced_revenue_target' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'balance' => 'decimal:2',
            'budget_approval_limit' => 'decimal:2',
            'sso_only' => 'boolean',
            'must_change_password' => 'boolean',
            'locked_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    private function uniqueSoftDeletedEmail(string $email): string
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $max = 255;
        $baseMax = max(1, $max - strlen($suffix));

        return substr($email, 0, $baseMax).$suffix;
    }
}
