<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SwitchCompany;

use App\Domain\Companies\Support\ActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SwitchCompany\SwitchCompanyRequest;
use Illuminate\Http\RedirectResponse;

final class SwitchCompanyController extends Controller
{
    public function __construct(
        private readonly ActiveCompany $activeCompany,
    ) {}

    public function __invoke(SwitchCompanyRequest $request): RedirectResponse
    {
        $this->activeCompany->switch(
            $request->user(),
            $request->integer('company_id'),
        );

        return back();
    }
}
