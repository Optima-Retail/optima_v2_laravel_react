<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Companies;

use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Services\CompanyService;
use App\Domain\Companies\Services\EstablishmentService;
use App\Domain\Config\Provinces\Services\ProvinceService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Companies\StoreCompanyRequest;
use App\Http\Requests\Web\Companies\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\Delegation;
use App\Models\Establishment;
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
    use ResolvesActiveCompany;

    public function __construct(
        private readonly CompanyService $companies,
        private readonly EstablishmentService $establishments,
        private readonly ProvinceService $provinces,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'kind' => $request->string('kind')->trim()->toString(),
            'is_active' => $request->string('is_active')->trim()->toString(),
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 25),
            ]),
        ];

        return Inertia::render('Companies/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Company::class) ?? false,
                'update' => $request->user()?->can('companies.update') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'tax_id', 'kind', 'is_active', 'created_at', 'member_since'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search', 'kind', 'is_active', 'created_from', 'created_to'],
        );

        $kind = (string) ($filters['kind'] ?? '');

        if ($kind !== '' && ! in_array($kind, CompanyKind::values(), true)) {
            $filters['kind'] = '';
        }

        return TabulatorResponse::fromPaginator(
            $this->companies->paginateForWeb($filters, member: $request->user()),
        );
    }

    /**
     * Async options for company SearchableSelect pickers (loaded on open / search).
     *
     * scope=party — all active companies except current owner (clients/techs/suppliers)
     * scope=client — customer companies of the active owner (establishments/contracts)
     * scope=all — all active companies (delegations)
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless(
            $user->can('viewAny', Company::class)
            || $user->can('viewAny', CompanyRelationship::class)
            || $user->can('create', CompanyRelationship::class)
            || $user->can('viewAny', Establishment::class)
            || $user->can('viewAny', Contract::class)
            || $user->can('viewAny', Delegation::class),
            403,
        );

        $scope = $request->string('scope')->trim()->toString() ?: 'party';
        $search = $request->string('search')->trim()->toString();
        $includeId = $request->filled('include_id') ? $request->integer('include_id') : null;
        $exceptId = $request->filled('except_id') ? $request->integer('except_id') : null;
        $limit = max(1, min($request->integer('limit', 50), 100));

        $options = match ($scope) {
            'client' => $this->establishments->searchClientCompanyOptions(
                $this->activeCompany($request),
                search: $search !== '' ? $search : null,
                includeId: $includeId,
                limit: $limit,
            ),
            'all' => $this->companies->searchOptions(
                search: $search !== '' ? $search : null,
                includeId: $includeId,
                limit: $limit,
            ),
            default => $this->companies->searchOptions(
                search: $search !== '' ? $search : null,
                exceptId: $exceptId ?? $this->activeCompany($request)->id,
                includeId: $includeId,
                limit: $limit,
            ),
        };

        return response()->json(['data' => $options]);
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
        $record = $this->companies->create($request->validated(), $request->user());

        return redirect()
            ->route('companies.edit', $record)
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
            ->route('companies.edit', $company)
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
