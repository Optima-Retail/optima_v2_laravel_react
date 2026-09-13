<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\EstimatePolicy;

trait AuthorizesEstimates
{
    protected function authorizeEstimate(string $ability, ?WorkOrder $estimate = null): void
    {
        /** @var User|null $user */
        $user = request()->user();
        abort_if($user === null, 403);

        $policy = app(EstimatePolicy::class);

        $allowed = match ($ability) {
            'viewAny', 'create' => $policy->{$ability}($user),
            default => $estimate !== null && $policy->{$ability}($user, $estimate),
        };

        abort_unless($allowed, 403);
    }
}
