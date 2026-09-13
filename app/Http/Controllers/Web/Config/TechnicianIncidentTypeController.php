<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\TechnicianIncidentTypes\Services\TechnicianIncidentTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\TechnicianIncidentTypes\StoreTechnicianIncidentTypeRequest;
use App\Http\Requests\Web\Config\TechnicianIncidentTypes\UpdateTechnicianIncidentTypeRequest;
use App\Models\TechnicianIncidentType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TechnicianIncidentTypeController extends Controller
{
    public function __construct(
        private readonly TechnicianIncidentTypeService $technicianIncidentTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TechnicianIncidentType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/TechnicianIncidentTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', TechnicianIncidentType::class) ?? false,
                'update' => $request->user()?->can('technician_incident_types.update') ?? false,
                'delete' => $request->user()?->can('technician_incident_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TechnicianIncidentType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'due_days'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->technicianIncidentTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', TechnicianIncidentType::class);

        return Inertia::render('Config/TechnicianIncidentTypes/Create');
    }

    public function store(StoreTechnicianIncidentTypeRequest $request): RedirectResponse
    {
        $this->technicianIncidentTypes->create([
            'name' => $request->string('name')->toString(),
            'due_days' => $request->integer('due_days'),
            'send_mail_to_technician' => $request->boolean('send_mail_to_technician'),
        ]);

        return redirect()
            ->route('config.technician-incident-types.index')
            ->with('success', 'technician_incident_type_created_successfully');
    }

    public function edit(Request $request, TechnicianIncidentType $technicianIncidentType): Response
    {
        $this->authorize('update', $technicianIncidentType);

        return Inertia::render('Config/TechnicianIncidentTypes/Edit', [
            'technicianIncidentType' => $this->technicianIncidentTypes->toFormData($technicianIncidentType),
            'can' => [
                'delete' => $request->user()?->can('delete', $technicianIncidentType) ?? false,
            ],
        ]);
    }

    public function update(
        UpdateTechnicianIncidentTypeRequest $request,
        TechnicianIncidentType $technicianIncidentType,
    ): RedirectResponse {
        $this->technicianIncidentTypes->update($technicianIncidentType, [
            'name' => $request->string('name')->toString(),
            'due_days' => $request->integer('due_days'),
            'send_mail_to_technician' => $request->boolean('send_mail_to_technician'),
        ]);

        return redirect()
            ->route('config.technician-incident-types.index')
            ->with('success', 'technician_incident_type_updated_successfully');
    }

    public function destroy(TechnicianIncidentType $technicianIncidentType): RedirectResponse
    {
        $this->authorize('delete', $technicianIncidentType);

        $this->technicianIncidentTypes->delete($technicianIncidentType);

        return redirect()
            ->route('config.technician-incident-types.index')
            ->with('success', 'technician_incident_type_deleted_successfully');
    }
}
