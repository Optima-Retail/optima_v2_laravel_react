<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\TechnicianRequestPriorities\Services\TechnicianRequestPriorityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\TechnicianRequestPriorities\StoreTechnicianRequestPriorityRequest;
use App\Http\Requests\Web\Config\TechnicianRequestPriorities\UpdateTechnicianRequestPriorityRequest;
use App\Models\TechnicianRequestPriority;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TechnicianRequestPriorityController extends Controller
{
    public function __construct(
        private readonly TechnicianRequestPriorityService $technicianRequestPriorities,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TechnicianRequestPriority::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 25),
            ]),
        ];

        return Inertia::render('Config/TechnicianRequestPriorities/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', TechnicianRequestPriority::class) ?? false,
                'update' => $request->user()?->can('technician_request_priorities.update') ?? false,
                'delete' => $request->user()?->can('technician_request_priorities.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TechnicianRequestPriority::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'key'],
            defaultSort: 'id',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->technicianRequestPriorities->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', TechnicianRequestPriority::class);

        return Inertia::render('Config/TechnicianRequestPriorities/Create');
    }

    public function store(StoreTechnicianRequestPriorityRequest $request): RedirectResponse
    {
        $record = $this->technicianRequestPriorities->create([
            'name' => $request->string('name')->toString(),
            'key' => $request->string('key')->toString(),
            'color' => $request->input('color'),
        ]);

        return redirect()
            ->route('config.technician-request-priorities.edit', $record)
            ->with('success', 'technician_request_priority_created_successfully');
    }

    public function edit(Request $request, TechnicianRequestPriority $technicianRequestPriority): Response
    {
        $this->authorize('update', $technicianRequestPriority);

        return Inertia::render('Config/TechnicianRequestPriorities/Edit', [
            'technicianRequestPriority' => $this->technicianRequestPriorities->toFormData($technicianRequestPriority),
            'can' => [
                'delete' => $request->user()?->can('delete', $technicianRequestPriority) ?? false,
            ],
        ]);
    }

    public function update(
        UpdateTechnicianRequestPriorityRequest $request,
        TechnicianRequestPriority $technicianRequestPriority,
    ): RedirectResponse {
        $this->technicianRequestPriorities->update($technicianRequestPriority, [
            'name' => $request->string('name')->toString(),
            'key' => $request->string('key')->toString(),
            'color' => $request->input('color'),
        ]);

        return redirect()
            ->route('config.technician-request-priorities.edit', $technicianRequestPriority)
            ->with('success', 'technician_request_priority_updated_successfully');
    }

    public function destroy(TechnicianRequestPriority $technicianRequestPriority): RedirectResponse
    {
        $this->authorize('delete', $technicianRequestPriority);

        $this->technicianRequestPriorities->delete($technicianRequestPriority);

        return redirect()
            ->route('config.technician-request-priorities.index')
            ->with('success', 'technician_request_priority_deleted_successfully');
    }
}
