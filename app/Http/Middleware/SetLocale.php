<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::normalize(
            $request->session()->get(Locale::SESSION_KEY)
            ?? $request->user()?->locale
            ?? $request->cookie(Locale::COOKIE_KEY)
        );

        app()->setLocale($locale);
        $request->session()->put(Locale::SESSION_KEY, $locale);

        return $next($request);
    }
}
