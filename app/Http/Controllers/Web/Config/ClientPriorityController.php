<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\ClientPriorities\Services\ClientPriorityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\ClientPriorities\StoreClientPriorityRequest;
use App\Http\Requests\Web\Config\ClientPriorities\UpdateClientPriorityRequest;
use App\Models\ClientPriority;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ClientPriorityController extends Controller
{
    public function __construct(
        private readonly ClientPriorityService $clientPriorities,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ClientPriority::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'level',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/ClientPriorities/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', ClientPriority::class) ?? false,
                'update' => $request->user()?->can('client_priorities.update') ?? false,
                'delete' => $request->user()?->can('client_priorities.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ClientPriority::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code', 'level'],
            defaultSort: 'level',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->clientPriorities->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', ClientPriority::class);

        return Inertia::render('Config/ClientPriorities/Create');
    }

    public function store(StoreClientPriorityRequest $request): RedirectResponse
    {
        $this->clientPriorities->create([
            'name' => $request->string('name')->toString(),
            'code' => $request->input('code'),
            'color' => $request->input('color'),
            'level' => $request->integer('level'),
        ]);

        return redirect()
            ->route('config.client-priorities.index')
            ->with('success', 'client_priority_created_successfully');
    }

    public function edit(Request $request, ClientPriority $clientPriority): Response
    {
        $this->authorize('update', $clientPriority);

        return Inertia::render('Config/ClientPriorities/Edit', [
            'clientPriority' => $this->clientPriorities->toFormData($clientPriority),
            'can' => [
                'delete' => $request->user()?->can('delete', $clientPriority) ?? false,
            ],
        ]);
    }

    public function update(UpdateClientPriorityRequest $request, ClientPriority $clientPriority): RedirectResponse
    {
        $this->clientPriorities->update($clientPriority, [
            'name' => $request->string('name')->toString(),
            'code' => $request->input('code'),
            'color' => $request->input('color'),
            'level' => $request->integer('level'),
        ]);

        return redirect()
            ->route('config.client-priorities.index')
            ->with('success', 'client_priority_updated_successfully');
    }

    public function destroy(ClientPriority $clientPriority): RedirectResponse
    {
        $this->authorize('delete', $clientPriority);

        $this->clientPriorities->delete($clientPriority);

        return redirect()
            ->route('config.client-priorities.index')
            ->with('success', 'client_priority_deleted_successfully');
    }
}
