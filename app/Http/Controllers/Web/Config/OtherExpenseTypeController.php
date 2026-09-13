<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\OtherExpenseTypes\Services\OtherExpenseTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\OtherExpenseTypes\StoreOtherExpenseTypeRequest;
use App\Http\Requests\Web\Config\OtherExpenseTypes\UpdateOtherExpenseTypeRequest;
use App\Models\OtherExpenseType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OtherExpenseTypeController extends Controller
{
    public function __construct(
        private readonly OtherExpenseTypeService $otherExpenseTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OtherExpenseType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/OtherExpenseTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', OtherExpenseType::class) ?? false,
                'update' => $request->user()?->can('other_expense_types.update') ?? false,
                'delete' => $request->user()?->can('other_expense_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OtherExpenseType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->otherExpenseTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', OtherExpenseType::class);

        return Inertia::render('Config/OtherExpenseTypes/Create');
    }

    public function store(StoreOtherExpenseTypeRequest $request): RedirectResponse
    {
        $this->otherExpenseTypes->create($request->validated());

        return redirect()
            ->route('config.other-expense-types.index')
            ->with('success', 'other_expense_type_created_successfully');
    }

    public function edit(Request $request, OtherExpenseType $otherExpenseType): Response
    {
        $this->authorize('update', $otherExpenseType);

        return Inertia::render('Config/OtherExpenseTypes/Edit', [
            'otherExpenseType' => $this->otherExpenseTypes->toFormData($otherExpenseType),
            'can' => [
                'delete' => $request->user()?->can('delete', $otherExpenseType) ?? false,
            ],
        ]);
    }

    public function update(UpdateOtherExpenseTypeRequest $request, OtherExpenseType $otherExpenseType): RedirectResponse
    {
        $this->otherExpenseTypes->update($otherExpenseType, $request->validated());

        return redirect()
            ->route('config.other-expense-types.index')
            ->with('success', 'other_expense_type_updated_successfully');
    }

    public function destroy(OtherExpenseType $otherExpenseType): RedirectResponse
    {
        $this->authorize('delete', $otherExpenseType);

        $this->otherExpenseTypes->delete($otherExpenseType);

        return redirect()
            ->route('config.other-expense-types.index')
            ->with('success', 'other_expense_type_deleted_successfully');
    }
}
