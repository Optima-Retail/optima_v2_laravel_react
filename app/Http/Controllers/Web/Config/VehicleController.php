<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Vehicles\Services\VehicleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Web\Config\Vehicles\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleService $vehicles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehicle::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'brand',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Vehicles/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Vehicle::class) ?? false,
                'update' => $request->user()?->can('vehicles.update') ?? false,
                'delete' => $request->user()?->can('vehicles.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'brand', 'model', 'license_plate', 'created_at'],
            defaultSort: 'brand',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->vehicles->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Vehicle::class);

        return Inertia::render('Config/Vehicles/Create', [
            'technicianOptions' => $this->vehicles->technicianOptions(),
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $this->vehicles->create($request->validated());

        return redirect()
            ->route('config.vehicles.index')
            ->with('success', 'vehicle_created_successfully');
    }

    public function edit(Request $request, Vehicle $vehicle): Response
    {
        $this->authorize('update', $vehicle);

        return Inertia::render('Config/Vehicles/Edit', [
            'vehicle' => $this->vehicles->toFormData($vehicle),
            'technicianOptions' => $this->vehicles->technicianOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $vehicle) ?? false,
            ],
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->vehicles->update($vehicle, $request->validated());

        return redirect()
            ->route('config.vehicles.index')
            ->with('success', 'vehicle_updated_successfully');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        $this->vehicles->delete($vehicle);

        return redirect()
            ->route('config.vehicles.index')
            ->with('success', 'vehicle_deleted_successfully');
    }
}
