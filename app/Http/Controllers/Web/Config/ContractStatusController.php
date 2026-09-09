<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\ContractStatuses\Services\ContractStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\ContractStatuses\StoreContractStatusRequest;
use App\Http\Requests\Web\Config\ContractStatuses\UpdateContractStatusRequest;
use App\Models\ContractStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ContractStatusController extends Controller
{
    public function __construct(
        private readonly ContractStatusService $contractStatuses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ContractStatus::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'lifecycle',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/ContractStatuses/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', ContractStatus::class) ?? false,
                'update' => $request->user()?->can('contract_statuses.update') ?? false,
                'delete' => $request->user()?->can('contract_statuses.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContractStatus::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'lifecycle', 'is_open'],
            defaultSort: 'lifecycle',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->contractStatuses->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', ContractStatus::class);

        return Inertia::render('Config/ContractStatuses/Create');
    }

    public function store(StoreContractStatusRequest $request): RedirectResponse
    {
        $this->contractStatuses->create([
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.contract-statuses.index')
            ->with('success', 'contract_status_created_successfully');
    }

    public function edit(Request $request, ContractStatus $contractStatus): Response
    {
        $this->authorize('update', $contractStatus);

        return Inertia::render('Config/ContractStatuses/Edit', [
            'contractStatus' => $this->contractStatuses->toFormData($contractStatus),
            'can' => [
                'delete' => $request->user()?->can('delete', $contractStatus) ?? false,
            ],
        ]);
    }

    public function update(UpdateContractStatusRequest $request, ContractStatus $contractStatus): RedirectResponse
    {
        $this->contractStatuses->update($contractStatus, [
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.contract-statuses.index')
            ->with('success', 'contract_status_updated_successfully');
    }

    public function destroy(ContractStatus $contractStatus): RedirectResponse
    {
        $this->authorize('delete', $contractStatus);

        $this->contractStatuses->delete($contractStatus);

        return redirect()
            ->route('config.contract-statuses.index')
            ->with('success', 'contract_status_deleted_successfully');
    }
}
