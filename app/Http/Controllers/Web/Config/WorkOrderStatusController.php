<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\WorkOrderStatuses\Services\WorkOrderStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\WorkOrderStatuses\StoreWorkOrderStatusRequest;
use App\Http\Requests\Web\Config\WorkOrderStatuses\UpdateWorkOrderStatusRequest;
use App\Models\WorkOrderStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WorkOrderStatusController extends Controller
{
    public function __construct(
        private readonly WorkOrderStatusService $workOrderStatuses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkOrderStatus::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'lifecycle',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/WorkOrderStatuses/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', WorkOrderStatus::class) ?? false,
                'update' => $request->user()?->can('work_order_statuses.update') ?? false,
                'delete' => $request->user()?->can('work_order_statuses.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrderStatus::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'lifecycle', 'is_open'],
            defaultSort: 'lifecycle',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->workOrderStatuses->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', WorkOrderStatus::class);

        return Inertia::render('Config/WorkOrderStatuses/Create');
    }

    public function store(StoreWorkOrderStatusRequest $request): RedirectResponse
    {
        $this->workOrderStatuses->create([
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.work-order-statuses.index')
            ->with('success', 'work_order_status_created_successfully');
    }

    public function edit(Request $request, WorkOrderStatus $workOrderStatus): Response
    {
        $this->authorize('update', $workOrderStatus);

        return Inertia::render('Config/WorkOrderStatuses/Edit', [
            'workOrderStatus' => $this->workOrderStatuses->toFormData($workOrderStatus),
            'can' => [
                'delete' => $request->user()?->can('delete', $workOrderStatus) ?? false,
            ],
        ]);
    }

    public function update(UpdateWorkOrderStatusRequest $request, WorkOrderStatus $workOrderStatus): RedirectResponse
    {
        $this->workOrderStatuses->update($workOrderStatus, [
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.work-order-statuses.index')
            ->with('success', 'work_order_status_updated_successfully');
    }

    public function destroy(WorkOrderStatus $workOrderStatus): RedirectResponse
    {
        $this->authorize('delete', $workOrderStatus);

        $this->workOrderStatuses->delete($workOrderStatus);

        return redirect()
            ->route('config.work-order-statuses.index')
            ->with('success', 'work_order_status_deleted_successfully');
    }
}
