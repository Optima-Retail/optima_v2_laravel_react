<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SsoProvider extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'driver',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_sso_identities')
            ->using(UserSsoIdentity::class)
            ->withPivot(['external_id'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_sso_provider_settings')
            ->using(TenantSsoProviderSetting::class)
            ->withPivot(['id', 'key', 'value'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TenantSsoProviderSetting, $this>
     */
    public function tenantSettings(): HasMany
    {
        return $this->hasMany(TenantSsoProviderSetting::class);
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::query()->where('slug', $slug)->first();
    }
}
