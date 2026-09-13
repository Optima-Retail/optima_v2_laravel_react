<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\TechnicianIncidentStatuses\Services\TechnicianIncidentStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\TechnicianIncidentStatuses\StoreTechnicianIncidentStatusRequest;
use App\Http\Requests\Web\Config\TechnicianIncidentStatuses\UpdateTechnicianIncidentStatusRequest;
use App\Models\TechnicianIncidentStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TechnicianIncidentStatusController extends Controller
{
    public function __construct(
        private readonly TechnicianIncidentStatusService $technicianIncidentStatuses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TechnicianIncidentStatus::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'lifecycle',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/TechnicianIncidentStatuses/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', TechnicianIncidentStatus::class) ?? false,
                'update' => $request->user()?->can('technician_incident_statuses.update') ?? false,
                'delete' => $request->user()?->can('technician_incident_statuses.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TechnicianIncidentStatus::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'lifecycle', 'is_open'],
            defaultSort: 'lifecycle',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->technicianIncidentStatuses->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', TechnicianIncidentStatus::class);

        return Inertia::render('Config/TechnicianIncidentStatuses/Create');
    }

    public function store(StoreTechnicianIncidentStatusRequest $request): RedirectResponse
    {
        $this->technicianIncidentStatuses->create([
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.technician-incident-statuses.index')
            ->with('success', 'technician_incident_status_created_successfully');
    }

    public function edit(Request $request, TechnicianIncidentStatus $technicianIncidentStatus): Response
    {
        $this->authorize('update', $technicianIncidentStatus);

        return Inertia::render('Config/TechnicianIncidentStatuses/Edit', [
            'technicianIncidentStatus' => $this->technicianIncidentStatuses->toFormData($technicianIncidentStatus),
            'can' => [
                'delete' => $request->user()?->can('delete', $technicianIncidentStatus) ?? false,
            ],
        ]);
    }

    public function update(
        UpdateTechnicianIncidentStatusRequest $request,
        TechnicianIncidentStatus $technicianIncidentStatus,
    ): RedirectResponse {
        $this->technicianIncidentStatuses->update($technicianIncidentStatus, [
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.technician-incident-statuses.index')
            ->with('success', 'technician_incident_status_updated_successfully');
    }

    public function destroy(TechnicianIncidentStatus $technicianIncidentStatus): RedirectResponse
    {
        $this->authorize('delete', $technicianIncidentStatus);

        $this->technicianIncidentStatuses->delete($technicianIncidentStatus);

        return redirect()
            ->route('config.technician-incident-statuses.index')
            ->with('success', 'technician_incident_status_deleted_successfully');
    }
}
