<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Integrations\Services\IntegrationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Integrations\StoreIntegrationRequest;
use App\Http\Requests\Web\Config\Integrations\UpdateIntegrationRequest;
use App\Models\Integration;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationService $integrations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Integration::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Integrations/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Integration::class) ?? false,
                'update' => $request->user()?->can('integrations.update') ?? false,
                'delete' => $request->user()?->can('integrations.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Integration::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->integrations->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Integration::class);

        return Inertia::render('Config/Integrations/Create');
    }

    public function store(StoreIntegrationRequest $request): RedirectResponse
    {
        $this->integrations->create($request->validated());

        return redirect()
            ->route('config.integrations.index')
            ->with('success', 'integration_created_successfully');
    }

    public function edit(Request $request, Integration $integration): Response
    {
        $this->authorize('update', $integration);

        return Inertia::render('Config/Integrations/Edit', [
            'integration' => $this->integrations->toFormData($integration),
            'can' => [
                'delete' => $request->user()?->can('delete', $integration) ?? false,
            ],
        ]);
    }

    public function update(UpdateIntegrationRequest $request, Integration $integration): RedirectResponse
    {
        $this->integrations->update($integration, $request->validated());

        return redirect()
            ->route('config.integrations.index')
            ->with('success', 'integration_updated_successfully');
    }

    public function destroy(Integration $integration): RedirectResponse
    {
        $this->authorize('delete', $integration);

        $this->integrations->delete($integration);

        return redirect()
            ->route('config.integrations.index')
            ->with('success', 'integration_deleted_successfully');
    }
}
