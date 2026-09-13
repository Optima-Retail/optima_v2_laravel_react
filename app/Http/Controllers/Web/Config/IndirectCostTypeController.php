<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\IndirectCostTypes\Services\IndirectCostTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\IndirectCostTypes\StoreIndirectCostTypeRequest;
use App\Http\Requests\Web\Config\IndirectCostTypes\UpdateIndirectCostTypeRequest;
use App\Models\IndirectCostType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IndirectCostTypeController extends Controller
{
    public function __construct(
        private readonly IndirectCostTypeService $indirectCostTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IndirectCostType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/IndirectCostTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', IndirectCostType::class) ?? false,
                'update' => $request->user()?->can('indirect_cost_types.update') ?? false,
                'delete' => $request->user()?->can('indirect_cost_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IndirectCostType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->indirectCostTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', IndirectCostType::class);

        return Inertia::render('Config/IndirectCostTypes/Create');
    }

    public function store(StoreIndirectCostTypeRequest $request): RedirectResponse
    {
        $this->indirectCostTypes->create($request->validated());

        return redirect()
            ->route('config.indirect-cost-types.index')
            ->with('success', 'indirect_cost_type_created_successfully');
    }

    public function edit(Request $request, IndirectCostType $indirectCostType): Response
    {
        $this->authorize('update', $indirectCostType);

        return Inertia::render('Config/IndirectCostTypes/Edit', [
            'indirectCostType' => $this->indirectCostTypes->toFormData($indirectCostType),
            'can' => [
                'delete' => $request->user()?->can('delete', $indirectCostType) ?? false,
            ],
        ]);
    }

    public function update(UpdateIndirectCostTypeRequest $request, IndirectCostType $indirectCostType): RedirectResponse
    {
        $this->indirectCostTypes->update($indirectCostType, $request->validated());

        return redirect()
            ->route('config.indirect-cost-types.index')
            ->with('success', 'indirect_cost_type_updated_successfully');
    }

    public function destroy(IndirectCostType $indirectCostType): RedirectResponse
    {
        $this->authorize('delete', $indirectCostType);

        $this->indirectCostTypes->delete($indirectCostType);

        return redirect()
            ->route('config.indirect-cost-types.index')
            ->with('success', 'indirect_cost_type_deleted_successfully');
    }
}
