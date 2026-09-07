<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Companies\Support\ActiveCompany;
use App\Support\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeCompany = app(ActiveCompany::class);
        $company = $user ? $activeCompany->forUser($user) : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => Locale::normalize($user->locale),
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                ] : null,
                'companies' => $user ? $activeCompany->membershipsForUser($user) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => app()->getLocale(),
            'supportedLocales' => Locale::options(),
            'app' => [
                'name' => config('app.name'),
                'apiVersion' => 'v3',
            ],
        ];
    }
}
