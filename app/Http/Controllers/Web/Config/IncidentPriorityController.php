<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\IncidentPriorities\Services\IncidentPriorityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\IncidentPriorities\StoreIncidentPriorityRequest;
use App\Http\Requests\Web\Config\IncidentPriorities\UpdateIncidentPriorityRequest;
use App\Models\IncidentPriority;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IncidentPriorityController extends Controller
{
    public function __construct(
        private readonly IncidentPriorityService $incidentPriorities,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IncidentPriority::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/IncidentPriorities/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', IncidentPriority::class) ?? false,
                'update' => $request->user()?->can('incident_priorities.update') ?? false,
                'delete' => $request->user()?->can('incident_priorities.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IncidentPriority::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'resolution_time_hours'],
            defaultSort: 'id',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->incidentPriorities->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', IncidentPriority::class);

        return Inertia::render('Config/IncidentPriorities/Create');
    }

    public function store(StoreIncidentPriorityRequest $request): RedirectResponse
    {
        $this->incidentPriorities->create([
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'resolution_time_hours' => $request->integer('resolution_time_hours'),
        ]);

        return redirect()
            ->route('config.incident-priorities.index')
            ->with('success', 'incident_priority_created_successfully');
    }

    public function edit(Request $request, IncidentPriority $incidentPriority): Response
    {
        $this->authorize('update', $incidentPriority);

        return Inertia::render('Config/IncidentPriorities/Edit', [
            'incidentPriority' => $this->incidentPriorities->toFormData($incidentPriority),
            'can' => [
                'delete' => $request->user()?->can('delete', $incidentPriority) ?? false,
            ],
        ]);
    }

    public function update(UpdateIncidentPriorityRequest $request, IncidentPriority $incidentPriority): RedirectResponse
    {
        $this->incidentPriorities->update($incidentPriority, [
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'resolution_time_hours' => $request->integer('resolution_time_hours'),
        ]);

        return redirect()
            ->route('config.incident-priorities.index')
            ->with('success', 'incident_priority_updated_successfully');
    }

    public function destroy(IncidentPriority $incidentPriority): RedirectResponse
    {
        $this->authorize('delete', $incidentPriority);

        $this->incidentPriorities->delete($incidentPriority);

        return redirect()
            ->route('config.incident-priorities.index')
            ->with('success', 'incident_priority_deleted_successfully');
    }
}
