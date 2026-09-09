<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\NumberingPatterns\StoreNumberingPatternRequest;
use App\Http\Requests\Web\Config\NumberingPatterns\UpdateNumberingPatternRequest;
use App\Http\Requests\Web\Config\NumberingPatterns\UpsertNumberingPatternByResourceRequest;
use App\Models\NumberingPattern;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NumberingPatternController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly NumberingPatternService $patterns,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', NumberingPattern::class);
        $this->activeCompany($request);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'resource',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/NumberingPatterns/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', NumberingPattern::class) ?? false,
                'update' => $request->user()?->can('numbering_patterns.update') ?? false,
                'delete' => $request->user()?->can('numbering_patterns.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NumberingPattern::class);

        $company = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'resource', 'is_active'],
            defaultSort: 'resource',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->patterns->paginateForWeb($company, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', NumberingPattern::class);
        $this->activeCompany($request);

        return Inertia::render('Config/NumberingPatterns/Create', [
            'resourceOptions' => $this->patterns->resourceOptions(),
            'segmentTypeOptions' => $this->patterns->segmentTypeOptions(),
            'defaultSegments' => $this->patterns->defaultSegmentsFor('contracts'),
        ]);
    }

    public function store(StoreNumberingPatternRequest $request): RedirectResponse
    {
        $company = $this->activeCompany($request);

        $this->patterns->create([
            'company_id' => $company->id,
            'resource' => $request->string('resource')->toString(),
            'segments' => $request->input('segments', []),
            'reset_yearly' => $request->boolean('reset_yearly'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('config.numbering-patterns.index')
            ->with('success', 'numbering_pattern_created_successfully');
    }

    public function edit(Request $request, NumberingPattern $numberingPattern): Response
    {
        $this->authorize('update', $numberingPattern);
        $this->assertPatternBelongsToActiveCompany($request, $numberingPattern);

        return Inertia::render('Config/NumberingPatterns/Edit', [
            'pattern' => $this->patterns->toFormData($numberingPattern),
            'segmentTypeOptions' => $this->patterns->segmentTypeOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $numberingPattern) ?? false,
            ],
        ]);
    }

    public function update(UpdateNumberingPatternRequest $request, NumberingPattern $numberingPattern): RedirectResponse
    {
        $this->assertPatternBelongsToActiveCompany($request, $numberingPattern);

        $this->patterns->update($numberingPattern, [
            'segments' => $request->input('segments', []),
            'reset_yearly' => $request->boolean('reset_yearly'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('config.numbering-patterns.index')
            ->with('success', 'numbering_pattern_updated_successfully');
    }

    public function destroy(Request $request, NumberingPattern $numberingPattern): RedirectResponse
    {
        $this->authorize('delete', $numberingPattern);
        $this->assertPatternBelongsToActiveCompany($request, $numberingPattern);

        $this->patterns->delete($numberingPattern);

        return redirect()
            ->route('config.numbering-patterns.index')
            ->with('success', 'numbering_pattern_deleted_successfully');
    }

    public function configure(Request $request, string $resource): Response
    {
        $this->assertKnownResource($resource);
        $company = $this->activeCompany($request);

        $pattern = $this->patterns->findForResource($company, $resource);

        if ($pattern !== null) {
            $this->authorize('update', $pattern);
            $form = $this->patterns->toFormData($pattern);
        } else {
            $this->authorize('create', NumberingPattern::class);
            $form = $this->patterns->defaultFormData($company, $resource);
        }

        return Inertia::render('Config/NumberingPatterns/Configure', [
            'pattern' => $form,
            'segmentTypeOptions' => $this->patterns->segmentTypeOptions(),
            'returnTo' => $request->string('return')->toString() ?: null,
        ]);
    }

    public function upsertByResource(UpsertNumberingPatternByResourceRequest $request, string $resource): RedirectResponse
    {
        $this->assertKnownResource($resource);
        $company = $this->activeCompany($request);

        $this->patterns->upsertForResource($company, $resource, [
            'segments' => $request->input('segments', []),
            'reset_yearly' => $request->boolean('reset_yearly'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $returnTo = $request->string('return')->toString();

        if ($returnTo !== '' && str_starts_with($returnTo, '/')) {
            return redirect($returnTo)
                ->with('success', 'numbering_pattern_saved_successfully');
        }

        return redirect()
            ->route('config.numbering-patterns.index')
            ->with('success', 'numbering_pattern_saved_successfully');
    }

    private function assertKnownResource(string $resource): void
    {
        if (! in_array($resource, NumberingResource::values(), true)) {
            throw new NotFoundHttpException;
        }
    }

    private function assertPatternBelongsToActiveCompany(Request $request, NumberingPattern $pattern): void
    {
        $company = $this->activeCompany($request);

        abort_if((int) $pattern->company_id !== (int) $company->id, 404);
    }
}
