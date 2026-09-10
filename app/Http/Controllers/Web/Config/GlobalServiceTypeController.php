<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\GlobalServiceTypes\Services\GlobalServiceTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\GlobalServiceTypes\StoreGlobalServiceTypeRequest;
use App\Http\Requests\Web\Config\GlobalServiceTypes\UpdateGlobalServiceTypeRequest;
use App\Models\GlobalServiceType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GlobalServiceTypeController extends Controller
{
    public function __construct(
        private readonly GlobalServiceTypeService $globalServiceTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GlobalServiceType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/GlobalServiceTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', GlobalServiceType::class) ?? false,
                'update' => $request->user()?->can('global_service_types.update') ?? false,
                'delete' => $request->user()?->can('global_service_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GlobalServiceType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->globalServiceTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', GlobalServiceType::class);

        return Inertia::render('Config/GlobalServiceTypes/Create');
    }

    public function store(StoreGlobalServiceTypeRequest $request): RedirectResponse
    {
        $this->globalServiceTypes->create($request->validated());

        return redirect()
            ->route('config.global-service-types.index')
            ->with('success', 'global_service_type_created_successfully');
    }

    public function edit(Request $request, GlobalServiceType $globalServiceType): Response
    {
        $this->authorize('update', $globalServiceType);

        return Inertia::render('Config/GlobalServiceTypes/Edit', [
            'globalServiceType' => $this->globalServiceTypes->toFormData($globalServiceType),
            'can' => [
                'delete' => $request->user()?->can('delete', $globalServiceType) ?? false,
            ],
        ]);
    }

    public function update(UpdateGlobalServiceTypeRequest $request, GlobalServiceType $globalServiceType): RedirectResponse
    {
        $this->globalServiceTypes->update($globalServiceType, $request->validated());

        return redirect()
            ->route('config.global-service-types.index')
            ->with('success', 'global_service_type_updated_successfully');
    }

    public function destroy(GlobalServiceType $globalServiceType): RedirectResponse
    {
        $this->authorize('delete', $globalServiceType);

        $this->globalServiceTypes->delete($globalServiceType);

        return redirect()
            ->route('config.global-service-types.index')
            ->with('success', 'global_service_type_deleted_successfully');
    }
}
