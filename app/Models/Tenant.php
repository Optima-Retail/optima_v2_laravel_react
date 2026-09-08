<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsToMany<SsoProvider, $this>
     */
    public function ssoProviders(): BelongsToMany
    {
        return $this->belongsToMany(SsoProvider::class, 'tenant_sso_provider_settings')
            ->using(TenantSsoProviderSetting::class)
            ->withPivot(['id', 'key', 'value'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TenantSsoProviderSetting, $this>
     */
    public function ssoProviderSettings(): HasMany
    {
        return $this->hasMany(TenantSsoProviderSetting::class);
    }
}
