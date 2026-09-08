<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Companies;

use App\Domain\Companies\Services\CompanyService;
use App\Domain\Companies\Services\EstablishmentService;
use App\Domain\Config\Provinces\Services\ProvinceService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Companies\StoreEstablishmentRequest;
use App\Http\Requests\Web\Companies\UpdateEstablishmentRequest;
use App\Models\Establishment;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EstablishmentController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly EstablishmentService $establishments,
        private readonly CompanyService $companies,
        private readonly ProvinceService $provinces,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Establishment::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Establishments/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Establishment::class) ?? false,
                'update' => $request->user()?->can('establishments.update') ?? false,
                'delete' => $request->user()?->can('establishments.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Establishment::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code', 'city'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->establishments->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Establishment::class);

        $owner = $this->activeCompany($request);

        return Inertia::render('Establishments/Create', [
            'defaultCompanyId' => $this->establishments->accessibleCompanyIds($owner)[0] ?? null,
            'companyOptions' => $this->establishments->clientCompanyOptions($owner),
            'countryOptions' => $this->companies->countryOptions(),
            'provinceOptions' => $this->provinces->options(),
            'timezoneOptions' => $this->establishments->timezoneOptions(),
            'delegationOptions' => $this->establishments->delegationOptions(),
            'languageOptions' => $this->establishments->languageOptions(),
            'establishmentTypeOptions' => $this->establishments->establishmentTypeOptions(),
            'seriesOptions' => $this->establishments->seriesOptions(),
            'userOptions' => $this->establishments->userOptions(),
        ]);
    }

    public function store(StoreEstablishmentRequest $request): RedirectResponse
    {
        $this->establishments->create($request->validated());

        return redirect()
            ->route('establishments.index')
            ->with('success', 'establishment_created_successfully');
    }

    public function edit(Request $request, Establishment $establishment): Response
    {
        $this->authorize('update', $establishment);

        $owner = $this->activeCompany($request);

        return Inertia::render('Establishments/Edit', [
            'establishment' => $this->establishments->toFormData($establishment),
            'companyOptions' => $this->establishments->clientCompanyOptions($owner),
            'countryOptions' => $this->companies->countryOptions(),
            'provinceOptions' => $this->provinces->options(),
            'timezoneOptions' => $this->establishments->timezoneOptions(),
            'delegationOptions' => $this->establishments->delegationOptions(),
            'languageOptions' => $this->establishments->languageOptions(),
            'establishmentTypeOptions' => $this->establishments->establishmentTypeOptions(),
            'seriesOptions' => $this->establishments->seriesOptions(),
            'userOptions' => $this->establishments->userOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $establishment) ?? false,
            ],
        ]);
    }

    public function update(UpdateEstablishmentRequest $request, Establishment $establishment): RedirectResponse
    {
        $this->establishments->update($establishment, $request->validated());

        return redirect()
            ->route('establishments.index')
            ->with('success', 'establishment_updated_successfully');
    }

    public function destroy(Establishment $establishment): RedirectResponse
    {
        $this->authorize('delete', $establishment);

        $this->establishments->delete($establishment);

        return redirect()
            ->route('establishments.index')
            ->with('success', 'establishment_deleted_successfully');
    }
}
