<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\TechnicianRequestStatuses\Services\TechnicianRequestStatusService;
use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\TechnicianRequestStatuses\StoreTechnicianRequestStatusRequest;
use App\Http\Requests\Web\Config\TechnicianRequestStatuses\UpdateTechnicianRequestStatusRequest;
use App\Models\TechnicianRequestStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TechnicianRequestStatusController extends Controller
{
    public function __construct(
        private readonly TechnicianRequestStatusService $technicianRequestStatuses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TechnicianRequestStatus::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'kind' => $request->string('kind')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'lifecycle',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/TechnicianRequestStatuses/Index', [
            'filters' => $filters,
            'kindOptions' => collect(TechnicianRequestStatusKind::cases())
                ->map(fn (TechnicianRequestStatusKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->value,
                ])
                ->values()
                ->all(),
            'can' => [
                'create' => $request->user()?->can('create', TechnicianRequestStatus::class) ?? false,
                'update' => $request->user()?->can('technician_request_statuses.update') ?? false,
                'delete' => $request->user()?->can('technician_request_statuses.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TechnicianRequestStatus::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'lifecycle', 'kind', 'is_open'],
            defaultSort: 'lifecycle',
            defaultDirection: 'asc',
            filterKeys: ['search', 'kind'],
        );

        return TabulatorResponse::fromPaginator(
            $this->technicianRequestStatuses->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', TechnicianRequestStatus::class);

        return Inertia::render('Config/TechnicianRequestStatuses/Create');
    }

    public function store(StoreTechnicianRequestStatusRequest $request): RedirectResponse
    {
        $this->technicianRequestStatuses->create([
            'kind' => $request->string('kind')->toString(),
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.technician-request-statuses.index')
            ->with('success', 'technician_request_status_created_successfully');
    }

    public function edit(Request $request, TechnicianRequestStatus $technicianRequestStatus): Response
    {
        $this->authorize('update', $technicianRequestStatus);

        return Inertia::render('Config/TechnicianRequestStatuses/Edit', [
            'technicianRequestStatus' => $this->technicianRequestStatuses->toFormData($technicianRequestStatus),
            'can' => [
                'delete' => $request->user()?->can('delete', $technicianRequestStatus) ?? false,
            ],
        ]);
    }

    public function update(
        UpdateTechnicianRequestStatusRequest $request,
        TechnicianRequestStatus $technicianRequestStatus,
    ): RedirectResponse {
        $this->technicianRequestStatuses->update($technicianRequestStatus, [
            'kind' => $request->string('kind')->toString(),
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.technician-request-statuses.index')
            ->with('success', 'technician_request_status_updated_successfully');
    }

    public function destroy(TechnicianRequestStatus $technicianRequestStatus): RedirectResponse
    {
        $this->authorize('delete', $technicianRequestStatus);

        $this->technicianRequestStatuses->delete($technicianRequestStatus);

        return redirect()
            ->route('config.technician-request-statuses.index')
            ->with('success', 'technician_request_status_deleted_successfully');
    }
}
