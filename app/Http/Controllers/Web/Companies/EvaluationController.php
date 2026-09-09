<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Companies;

use App\Domain\Evaluations\Services\EvaluationService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Companies\UpdateEvaluationRequest;
use App\Models\Evaluation;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EvaluationController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly EvaluationService $evaluations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Evaluation::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Evaluations/Index', [
            'filters' => $filters,
            'can' => [
                'update' => $request->user()?->can('evaluations.update') ?? false,
                'delete' => $request->user()?->can('evaluations.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Evaluation::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'subject', 'next_action_at', 'visit_count', 'call_count', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->evaluations->paginateForWeb($owner, $filters),
        );
    }

    public function edit(Request $request, Evaluation $evaluation): Response
    {
        $this->authorize('update', $evaluation);

        $owner = $this->activeCompany($request);
        $user = $request->user();

        return Inertia::render('Evaluations/Edit', [
            'evaluation' => $this->evaluations->toFormData($evaluation),
            'evaluationStatusOptions' => $this->evaluations->evaluationStatusOptions(
                $evaluation->evaluation_status_id !== null ? (int) $evaluation->evaluation_status_id : null,
            ),
            'userOptions' => $this->evaluations->userOptions(),
            'establishmentOptions' => $this->evaluations->establishmentOptions($owner),
            'can' => [
                'delete' => $user?->can('delete', $evaluation) ?? false,
            ],
        ]);
    }

    public function update(UpdateEvaluationRequest $request, Evaluation $evaluation): RedirectResponse
    {
        $this->evaluations->update($evaluation, $request->validated());

        return redirect()
            ->route('evaluations.index')
            ->with('success', 'evaluation_updated_successfully');
    }

    public function destroy(Evaluation $evaluation): RedirectResponse
    {
        $this->authorize('delete', $evaluation);

        $this->evaluations->delete($evaluation);

        return redirect()
            ->route('evaluations.index')
            ->with('success', 'evaluation_deleted_successfully');
    }
}
