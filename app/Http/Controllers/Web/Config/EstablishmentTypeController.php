<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\EstablishmentTypes\Services\EstablishmentTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\EstablishmentTypes\StoreEstablishmentTypeRequest;
use App\Http\Requests\Web\Config\EstablishmentTypes\UpdateEstablishmentTypeRequest;
use App\Models\EstablishmentType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EstablishmentTypeController extends Controller
{
    public function __construct(
        private readonly EstablishmentTypeService $establishmentTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EstablishmentType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/EstablishmentTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', EstablishmentType::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EstablishmentType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code', 'health_and_safety_delay_days'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->establishmentTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', EstablishmentType::class);

        return Inertia::render('Config/EstablishmentTypes/Create');
    }

    public function store(StoreEstablishmentTypeRequest $request): RedirectResponse
    {
        $this->establishmentTypes->create($request->validated());

        return redirect()
            ->route('config.establishment-types.index')
            ->with('success', 'establishment_type_created_successfully');
    }

    public function edit(Request $request, EstablishmentType $establishmentType): Response
    {
        $this->authorize('update', $establishmentType);

        return Inertia::render('Config/EstablishmentTypes/Edit', [
            'establishmentType' => $this->establishmentTypes->toFormData($establishmentType),
            'can' => [
                'delete' => $request->user()?->can('delete', $establishmentType) ?? false,
            ],
        ]);
    }

    public function update(UpdateEstablishmentTypeRequest $request, EstablishmentType $establishmentType): RedirectResponse
    {
        $this->establishmentTypes->update($establishmentType, $request->validated());

        return redirect()
            ->route('config.establishment-types.index')
            ->with('success', 'establishment_type_updated_successfully');
    }

    public function destroy(EstablishmentType $establishmentType): RedirectResponse
    {
        $this->authorize('delete', $establishmentType);

        $this->establishmentTypes->delete($establishmentType);

        return redirect()
            ->route('config.establishment-types.index')
            ->with('success', 'establishment_type_deleted_successfully');
    }
}
