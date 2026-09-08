<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserSsoIdentity extends Pivot
{
    protected $table = 'user_sso_identities';

    public $incrementing = false;

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'sso_provider_id',
        'external_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SsoProvider, $this>
     */
    public function ssoProvider(): BelongsTo
    {
        return $this->belongsTo(SsoProvider::class);
    }
}
