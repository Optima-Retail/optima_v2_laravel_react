<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\IncidentTypes\Services\IncidentTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\IncidentTypes\StoreIncidentTypeRequest;
use App\Http\Requests\Web\Config\IncidentTypes\UpdateIncidentTypeRequest;
use App\Models\IncidentPriority;
use App\Models\IncidentType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IncidentTypeController extends Controller
{
    public function __construct(
        private readonly IncidentTypeService $incidentTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IncidentType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/IncidentTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', IncidentType::class) ?? false,
                'update' => $request->user()?->can('incident_types.update') ?? false,
                'delete' => $request->user()?->can('incident_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IncidentType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'id',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->incidentTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', IncidentType::class);

        return Inertia::render('Config/IncidentTypes/Create', [
            'incidentPriorityOptions' => $this->incidentPriorityOptions(),
        ]);
    }

    public function store(StoreIncidentTypeRequest $request): RedirectResponse
    {
        $this->incidentTypes->create($request->validated());

        return redirect()
            ->route('config.incident-types.index')
            ->with('success', 'incident_type_created_successfully');
    }

    public function edit(Request $request, IncidentType $incidentType): Response
    {
        $this->authorize('update', $incidentType);

        return Inertia::render('Config/IncidentTypes/Edit', [
            'incidentType' => $this->incidentTypes->toFormData($incidentType),
            'incidentPriorityOptions' => $this->incidentPriorityOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $incidentType) ?? false,
            ],
        ]);
    }

    public function update(UpdateIncidentTypeRequest $request, IncidentType $incidentType): RedirectResponse
    {
        $this->incidentTypes->update($incidentType, $request->validated());

        return redirect()
            ->route('config.incident-types.index')
            ->with('success', 'incident_type_updated_successfully');
    }

    public function destroy(IncidentType $incidentType): RedirectResponse
    {
        $this->authorize('delete', $incidentType);

        $this->incidentTypes->delete($incidentType);

        return redirect()
            ->route('config.incident-types.index')
            ->with('success', 'incident_type_deleted_successfully');
    }

    /**
     * @return list<array{id: int, label: string, color: string|null}>
     */
    private function incidentPriorityOptions(): array
    {
        return IncidentPriority::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (IncidentPriority $priority): array => [
                'id' => $priority->id,
                'label' => $priority->name,
                'color' => $priority->color,
            ])
            ->values()
            ->all();
    }
}
