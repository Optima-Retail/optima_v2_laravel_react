<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Companies;

use App\Domain\Companies\Services\CompanyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Companies\AttachCompanyUserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class CompanyMemberController extends Controller
{
    public function __construct(
        private readonly CompanyService $companies,
    ) {}

    public function store(AttachCompanyUserRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $this->companies->attachUser($company, (int) $request->validated('user_id'));

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', 'company_user_assigned_successfully');
    }

    public function destroy(Company $company, User $user): RedirectResponse
    {
        $this->authorize('update', $company);

        $this->companies->detachUser($company, $user);

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', 'company_user_unlinked_successfully');
    }
}
