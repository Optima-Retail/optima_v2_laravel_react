<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Companies\Support\ActiveCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyContext
{
    public function __construct(
        private readonly ActiveCompany $activeCompany,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->activeCompany->forUser($request->user());

        abort_if($company === null, 403);

        $request->attributes->set('activeCompany', $company);

        return $next($request);
    }
}
