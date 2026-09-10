<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\ServiceTypes\Services\ServiceTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\ServiceTypes\StoreServiceTypeRequest;
use App\Http\Requests\Web\Config\ServiceTypes\UpdateServiceTypeRequest;
use App\Models\ServiceType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceTypeController extends Controller
{
    public function __construct(
        private readonly ServiceTypeService $serviceTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServiceType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/ServiceTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', ServiceType::class) ?? false,
                'update' => $request->user()?->can('service_types.update') ?? false,
                'delete' => $request->user()?->can('service_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->serviceTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', ServiceType::class);

        return Inertia::render('Config/ServiceTypes/Create');
    }

    public function store(StoreServiceTypeRequest $request): RedirectResponse
    {
        $this->serviceTypes->create($request->validated());

        return redirect()
            ->route('config.service-types.index')
            ->with('success', 'service_type_created_successfully');
    }

    public function edit(Request $request, ServiceType $serviceType): Response
    {
        $this->authorize('update', $serviceType);

        return Inertia::render('Config/ServiceTypes/Edit', [
            'serviceType' => $this->serviceTypes->toFormData($serviceType),
            'can' => [
                'delete' => $request->user()?->can('delete', $serviceType) ?? false,
            ],
        ]);
    }

    public function update(UpdateServiceTypeRequest $request, ServiceType $serviceType): RedirectResponse
    {
        $this->serviceTypes->update($serviceType, $request->validated());

        return redirect()
            ->route('config.service-types.index')
            ->with('success', 'service_type_updated_successfully');
    }

    public function destroy(ServiceType $serviceType): RedirectResponse
    {
        $this->authorize('delete', $serviceType);

        $this->serviceTypes->delete($serviceType);

        return redirect()
            ->route('config.service-types.index')
            ->with('success', 'service_type_deleted_successfully');
    }
}
