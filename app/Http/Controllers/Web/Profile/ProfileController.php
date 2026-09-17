<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Profile\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $user?->loadMissing(['roles', 'timezone', 'team', 'brand']);

        return Inertia::render('Profile/Index', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatarUrl(),
                'username' => $user->username,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->getRoleNames()->values()->all(),
                'timezone' => $user->timezone?->name,
                'team' => $user->team?->name,
                'brand' => $user->brand?->name,
            ],
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        return redirect()
            ->route('profile.show')
            ->with('success', 'password_updated_successfully');
    }
}
