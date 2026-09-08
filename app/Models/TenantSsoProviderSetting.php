<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantSsoProviderSetting extends Pivot
{
    protected $table = 'tenant_sso_provider_settings';

    public $incrementing = true;

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sso_provider_id',
        'key',
        'value',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<SsoProvider, $this>
     */
    public function ssoProvider(): BelongsTo
    {
        return $this->belongsTo(SsoProvider::class);
    }
}
