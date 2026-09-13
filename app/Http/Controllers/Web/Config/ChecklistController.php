<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Checklists\Services\ChecklistService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Checklists\StoreChecklistRequest;
use App\Http\Requests\Web\Config\Checklists\UpdateChecklistRequest;
use App\Models\Checklist;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistService $checklists,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Checklist::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'sort_order',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Checklists/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Checklist::class) ?? false,
                'update' => $request->user()?->can('checklists.update') ?? false,
                'delete' => $request->user()?->can('checklists.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Checklist::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'label', 'document_type', 'sort_order', 'requires_validation'],
            defaultSort: 'sort_order',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->checklists->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Checklist::class);

        return Inertia::render('Config/Checklists/Create', [
            'documentTypeOptions' => $this->checklists->documentTypeOptions(),
            'workOrderStatusOptions' => $this->checklists->workOrderStatusOptions(),
            'estimateStatusOptions' => $this->checklists->estimateStatusOptions(),
        ]);
    }

    public function store(StoreChecklistRequest $request): RedirectResponse
    {
        $this->checklists->create($request->validated());

        return redirect()
            ->route('config.checklists.index')
            ->with('success', 'checklist_created_successfully');
    }

    public function edit(Request $request, Checklist $checklist): Response
    {
        $this->authorize('update', $checklist);

        return Inertia::render('Config/Checklists/Edit', [
            'checklist' => $this->checklists->toFormData($checklist),
            'documentTypeOptions' => $this->checklists->documentTypeOptions(),
            'workOrderStatusOptions' => $this->checklists->workOrderStatusOptions(),
            'estimateStatusOptions' => $this->checklists->estimateStatusOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $checklist) ?? false,
            ],
        ]);
    }

    public function update(UpdateChecklistRequest $request, Checklist $checklist): RedirectResponse
    {
        $this->checklists->update($checklist, $request->validated());

        return redirect()
            ->route('config.checklists.index')
            ->with('success', 'checklist_updated_successfully');
    }

    public function destroy(Checklist $checklist): RedirectResponse
    {
        $this->authorize('delete', $checklist);

        $this->checklists->delete($checklist);

        return redirect()
            ->route('config.checklists.index')
            ->with('success', 'checklist_deleted_successfully');
    }
}
