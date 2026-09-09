<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\EvaluationStatuses\Services\EvaluationStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\EvaluationStatuses\StoreEvaluationStatusRequest;
use App\Http\Requests\Web\Config\EvaluationStatuses\UpdateEvaluationStatusRequest;
use App\Models\EvaluationStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EvaluationStatusController extends Controller
{
    public function __construct(
        private readonly EvaluationStatusService $evaluationStatuses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EvaluationStatus::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'lifecycle',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/EvaluationStatuses/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', EvaluationStatus::class) ?? false,
                'update' => $request->user()?->can('evaluation_statuses.update') ?? false,
                'delete' => $request->user()?->can('evaluation_statuses.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EvaluationStatus::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'lifecycle', 'is_open'],
            defaultSort: 'lifecycle',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->evaluationStatuses->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', EvaluationStatus::class);

        return Inertia::render('Config/EvaluationStatuses/Create');
    }

    public function store(StoreEvaluationStatusRequest $request): RedirectResponse
    {
        $this->evaluationStatuses->create([
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.evaluation-statuses.index')
            ->with('success', 'evaluation_status_created_successfully');
    }

    public function edit(Request $request, EvaluationStatus $evaluationStatus): Response
    {
        $this->authorize('update', $evaluationStatus);

        return Inertia::render('Config/EvaluationStatuses/Edit', [
            'evaluationStatus' => $this->evaluationStatuses->toFormData($evaluationStatus),
            'can' => [
                'delete' => $request->user()?->can('delete', $evaluationStatus) ?? false,
            ],
        ]);
    }

    public function update(UpdateEvaluationStatusRequest $request, EvaluationStatus $evaluationStatus): RedirectResponse
    {
        $this->evaluationStatuses->update($evaluationStatus, [
            'name' => $request->string('name')->toString(),
            'color' => $request->input('color'),
            'lifecycle' => $request->input('lifecycle'),
            'is_open' => $request->boolean('is_open'),
        ]);

        return redirect()
            ->route('config.evaluation-statuses.index')
            ->with('success', 'evaluation_status_updated_successfully');
    }

    public function destroy(EvaluationStatus $evaluationStatus): RedirectResponse
    {
        $this->authorize('delete', $evaluationStatus);

        $this->evaluationStatuses->delete($evaluationStatus);

        return redirect()
            ->route('config.evaluation-statuses.index')
            ->with('success', 'evaluation_status_deleted_successfully');
    }
}
