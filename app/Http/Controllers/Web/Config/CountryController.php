<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Countries\Services\CountryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Countries\StoreCountryRequest;
use App\Http\Requests\Web\Config\Countries\UpdateCountryRequest;
use App\Models\Country;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CountryController extends Controller
{
    public function __construct(
        private readonly CountryService $countries,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Country::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Countries/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Country::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Country::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'iso_code', 'timezone_id'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->countries->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Country::class);

        return Inertia::render('Config/Countries/Create', [
            'timezoneOptions' => $this->countries->timezoneOptions(),
        ]);
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        $this->countries->create($request->validated());

        return redirect()
            ->route('config.countries.index')
            ->with('success', 'country_created_successfully');
    }

    public function edit(Request $request, Country $country): Response
    {
        $this->authorize('update', $country);

        return Inertia::render('Config/Countries/Edit', [
            'country' => $this->countries->toFormData($country),
            'timezoneOptions' => $this->countries->timezoneOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $country) ?? false,
            ],
        ]);
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $this->countries->update($country, $request->validated());

        return redirect()
            ->route('config.countries.index')
            ->with('success', 'country_updated_successfully');
    }

    public function destroy(Country $country): RedirectResponse
    {
        $this->authorize('delete', $country);

        $this->countries->delete($country);

        return redirect()
            ->route('config.countries.index')
            ->with('success', 'country_deleted_successfully');
    }
}
