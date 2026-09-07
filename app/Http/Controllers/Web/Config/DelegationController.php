<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Delegations\Services\DelegationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Delegations\StoreDelegationRequest;
use App\Http\Requests\Web\Config\Delegations\UpdateDelegationRequest;
use App\Models\Delegation;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DelegationController extends Controller
{
    public function __construct(
        private readonly DelegationService $delegations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Delegation::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Delegations/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Delegation::class) ?? false,
                'update' => $request->user()?->can('delegations.update') ?? false,
                'delete' => $request->user()?->can('delegations.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Delegation::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'tax_id', 'company_id', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->delegations->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Delegation::class);

        return Inertia::render('Config/Delegations/Create', [
            'companyOptions' => $this->delegations->companyOptions(),
            'currencyOptions' => $this->delegations->currencyOptions(),
            'countryOptions' => $this->delegations->countryOptions(),
            'seriesOptions' => $this->delegations->seriesOptions(),
        ]);
    }

    public function store(StoreDelegationRequest $request): RedirectResponse
    {
        $this->delegations->create($request->validated());

        return redirect()
            ->route('config.delegations.index')
            ->with('success', 'delegation_created_successfully');
    }

    public function edit(Request $request, Delegation $delegation): Response
    {
        $this->authorize('update', $delegation);

        return Inertia::render('Config/Delegations/Edit', [
            'delegation' => $this->delegations->toFormData($delegation),
            'companyOptions' => $this->delegations->companyOptions(),
            'currencyOptions' => $this->delegations->currencyOptions(),
            'countryOptions' => $this->delegations->countryOptions(),
            'seriesOptions' => $this->delegations->seriesOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $delegation) ?? false,
            ],
        ]);
    }

    public function update(UpdateDelegationRequest $request, Delegation $delegation): RedirectResponse
    {
        $this->delegations->update($delegation, $request->validated());

        return redirect()
            ->route('config.delegations.index')
            ->with('success', 'delegation_updated_successfully');
    }

    public function destroy(Delegation $delegation): RedirectResponse
    {
        $this->authorize('delete', $delegation);

        $this->delegations->delete($delegation);

        return redirect()
            ->route('config.delegations.index')
            ->with('success', 'delegation_deleted_successfully');
    }
}
