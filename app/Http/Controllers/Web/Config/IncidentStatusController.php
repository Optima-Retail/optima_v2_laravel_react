<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\IncidentStatuses\Services\IncidentStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\IncidentStatuses\StoreIncidentStatusRequest;
use App\Http\Requests\Web\Config\IncidentStatuses\UpdateIncidentStatusRequest;
use App\Models\IncidentStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IncidentStatusController extends Controller
{
    public function __construct(
        private readonly IncidentStatusService $incidentStatuses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IncidentStatus::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'lifecycle',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/IncidentStatuses/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', IncidentStatus::class) ?? false,
                'update' => $request->user()?->can('incident_statuses.update') ?? false,
                'delete' => $request->user()?->can('incident_statuses.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IncidentStatus::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'lifecycle', 'is_open'],
            defaultSort: 'lifecycle',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->incidentStatuses->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', IncidentStatus::class);

        return Inertia::render('Config/IncidentStatuses/Create');
    }

    public function store(StoreIncidentStatusRequest $request): RedirectResponse
    {
        $this->incidentStatuses->create([
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.incident-statuses.index')
            ->with('success', 'incident_status_created_successfully');
    }

    public function edit(Request $request, IncidentStatus $incidentStatus): Response
    {
        $this->authorize('update', $incidentStatus);

        return Inertia::render('Config/IncidentStatuses/Edit', [
            'incidentStatus' => $this->incidentStatuses->toFormData($incidentStatus),
            'can' => [
                'delete' => $request->user()?->can('delete', $incidentStatus) ?? false,
            ],
        ]);
    }

    public function update(UpdateIncidentStatusRequest $request, IncidentStatus $incidentStatus): RedirectResponse
    {
        $this->incidentStatuses->update($incidentStatus, [
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.incident-statuses.index')
            ->with('success', 'incident_status_updated_successfully');
    }

    public function destroy(IncidentStatus $incidentStatus): RedirectResponse
    {
        $this->authorize('delete', $incidentStatus);

        $this->incidentStatuses->delete($incidentStatus);

        return redirect()
            ->route('config.incident-statuses.index')
            ->with('success', 'incident_status_deleted_successfully');
    }
}
