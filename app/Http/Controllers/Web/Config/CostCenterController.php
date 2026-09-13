<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\CostCenters\Services\CostCenterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\CostCenters\StoreCostCenterRequest;
use App\Http\Requests\Web\Config\CostCenters\UpdateCostCenterRequest;
use App\Models\CostCenter;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CostCenterController extends Controller
{
    public function __construct(
        private readonly CostCenterService $costCenters,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CostCenter::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/CostCenters/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', CostCenter::class) ?? false,
                'update' => $request->user()?->can('cost_centers.update') ?? false,
                'delete' => $request->user()?->can('cost_centers.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CostCenter::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->costCenters->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', CostCenter::class);

        return Inertia::render('Config/CostCenters/Create');
    }

    public function store(StoreCostCenterRequest $request): RedirectResponse
    {
        $this->costCenters->create($request->validated());

        return redirect()
            ->route('config.cost-centers.index')
            ->with('success', 'cost_center_created_successfully');
    }

    public function edit(Request $request, CostCenter $costCenter): Response
    {
        $this->authorize('update', $costCenter);

        return Inertia::render('Config/CostCenters/Edit', [
            'costCenter' => $this->costCenters->toFormData($costCenter),
            'can' => [
                'delete' => $request->user()?->can('delete', $costCenter) ?? false,
            ],
        ]);
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter): RedirectResponse
    {
        $this->costCenters->update($costCenter, $request->validated());

        return redirect()
            ->route('config.cost-centers.index')
            ->with('success', 'cost_center_updated_successfully');
    }

    public function destroy(CostCenter $costCenter): RedirectResponse
    {
        $this->authorize('delete', $costCenter);

        $this->costCenters->delete($costCenter);

        return redirect()
            ->route('config.cost-centers.index')
            ->with('success', 'cost_center_deleted_successfully');
    }
}
