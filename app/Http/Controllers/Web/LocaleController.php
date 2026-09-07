<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Locale\UpdateLocaleRequest;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;

final class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = Locale::normalize($request->string('locale')->toString());

        $request->session()->put(Locale::SESSION_KEY, $locale);

        $user = $request->user();
        if ($user !== null) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return redirect()
            ->back()
            ->cookie(Locale::COOKIE_KEY, $locale, 60 * 24 * 365);
    }
}
