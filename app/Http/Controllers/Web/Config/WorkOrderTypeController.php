<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\WorkOrderTypes\Services\WorkOrderTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\WorkOrderTypes\StoreWorkOrderTypeRequest;
use App\Http\Requests\Web\Config\WorkOrderTypes\UpdateWorkOrderTypeRequest;
use App\Models\WorkOrderType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WorkOrderTypeController extends Controller
{
    public function __construct(
        private readonly WorkOrderTypeService $workOrderTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkOrderType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/WorkOrderTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', WorkOrderType::class) ?? false,
                'update' => $request->user()?->can('work_order_types.update') ?? false,
                'delete' => $request->user()?->can('work_order_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrderType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->workOrderTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', WorkOrderType::class);

        return Inertia::render('Config/WorkOrderTypes/Create');
    }

    public function store(StoreWorkOrderTypeRequest $request): RedirectResponse
    {
        $this->workOrderTypes->create([
            'name' => $request->string('name')->toString(),
            'code' => $request->input('code'),
            'color' => $request->input('color'),
        ]);

        return redirect()
            ->route('config.work-order-types.index')
            ->with('success', 'work_order_type_created_successfully');
    }

    public function edit(Request $request, WorkOrderType $workOrderType): Response
    {
        $this->authorize('update', $workOrderType);

        return Inertia::render('Config/WorkOrderTypes/Edit', [
            'workOrderType' => $this->workOrderTypes->toFormData($workOrderType),
            'can' => [
                'delete' => $request->user()?->can('delete', $workOrderType) ?? false,
            ],
        ]);
    }

    public function update(UpdateWorkOrderTypeRequest $request, WorkOrderType $workOrderType): RedirectResponse
    {
        $this->workOrderTypes->update($workOrderType, [
            'name' => $request->string('name')->toString(),
            'code' => $request->input('code'),
            'color' => $request->input('color'),
        ]);

        return redirect()
            ->route('config.work-order-types.index')
            ->with('success', 'work_order_type_updated_successfully');
    }

    public function destroy(WorkOrderType $workOrderType): RedirectResponse
    {
        $this->authorize('delete', $workOrderType);

        $this->workOrderTypes->delete($workOrderType);

        return redirect()
            ->route('config.work-order-types.index')
            ->with('success', 'work_order_type_deleted_successfully');
    }
}
