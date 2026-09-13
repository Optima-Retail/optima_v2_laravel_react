<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\ExpenseTypes\Services\ExpenseTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\ExpenseTypes\StoreExpenseTypeRequest;
use App\Http\Requests\Web\Config\ExpenseTypes\UpdateExpenseTypeRequest;
use App\Models\ExpenseType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ExpenseTypeController extends Controller
{
    public function __construct(
        private readonly ExpenseTypeService $expenseTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExpenseType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/ExpenseTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', ExpenseType::class) ?? false,
                'update' => $request->user()?->can('expense_types.update') ?? false,
                'delete' => $request->user()?->can('expense_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExpenseType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->expenseTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', ExpenseType::class);

        return Inertia::render('Config/ExpenseTypes/Create');
    }

    public function store(StoreExpenseTypeRequest $request): RedirectResponse
    {
        $this->expenseTypes->create($request->validated());

        return redirect()
            ->route('config.expense-types.index')
            ->with('success', 'expense_type_created_successfully');
    }

    public function edit(Request $request, ExpenseType $expenseType): Response
    {
        $this->authorize('update', $expenseType);

        return Inertia::render('Config/ExpenseTypes/Edit', [
            'expenseType' => $this->expenseTypes->toFormData($expenseType),
            'can' => [
                'delete' => $request->user()?->can('delete', $expenseType) ?? false,
            ],
        ]);
    }

    public function update(UpdateExpenseTypeRequest $request, ExpenseType $expenseType): RedirectResponse
    {
        $this->expenseTypes->update($expenseType, $request->validated());

        return redirect()
            ->route('config.expense-types.index')
            ->with('success', 'expense_type_updated_successfully');
    }

    public function destroy(ExpenseType $expenseType): RedirectResponse
    {
        $this->authorize('delete', $expenseType);

        $this->expenseTypes->delete($expenseType);

        return redirect()
            ->route('config.expense-types.index')
            ->with('success', 'expense_type_deleted_successfully');
    }
}
