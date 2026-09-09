<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Companies;

use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Services\CompanyService;
use App\Domain\Config\Provinces\Services\ProvinceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Companies\StoreCompanyRequest;
use App\Http\Requests\Web\Companies\UpdateCompanyRequest;
use App\Models\Company;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companies,
        private readonly ProvinceService $provinces,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'kind' => $request->string('kind')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Companies/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Company::class) ?? false,
                'update' => $request->user()?->can('companies.update') ?? false,
                'delete' => $request->user()?->can('companies.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'tax_id', 'kind', 'is_active', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search', 'kind'],
        );

        $kind = (string) ($filters['kind'] ?? '');

        if ($kind !== '' && ! in_array($kind, CompanyKind::values(), true)) {
            $filters['kind'] = '';
        }

        return TabulatorResponse::fromPaginator(
            $this->companies->paginateForWeb($filters, member: $request->user()),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Company::class);

        return Inertia::render('Companies/Create', [
            'countryOptions' => $this->companies->countryOptions(),
            'provinceOptions' => $this->provinces->options(),
            'brandOptions' => $this->companies->brandOptions(),
            'languageOptions' => $this->companies->languageOptions(),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $this->companies->create($request->validated(), $request->user());

        return redirect()
            ->route('companies.index')
            ->with('success', 'company_created_successfully');
    }

    public function edit(Request $request, Company $company): Response
    {
        $this->authorize('update', $company);

        return Inertia::render('Companies/Edit', [
            'company' => $this->companies->toFormData($company),
            'countryOptions' => $this->companies->countryOptions(),
            'provinceOptions' => $this->provinces->options(),
            'brandOptions' => $this->companies->brandOptions(),
            'languageOptions' => $this->companies->languageOptions(),
            'members' => $this->companies->members($company),
            'assignableUserOptions' => $this->companies->assignableUserOptions($company),
            'can' => [
                'delete' => $request->user()?->can('delete', $company) ?? false,
                'manage_users' => $request->user()?->can('update', $company) ?? false,
            ],
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $this->companies->update($company, $request->validated());

        return redirect()
            ->route('companies.index')
            ->with('success', 'company_updated_successfully');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $this->companies->delete($company);

        return redirect()
            ->route('companies.index')
            ->with('success', 'company_deleted_successfully');
    }
}
