<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\RatingTypes\Services\RatingTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\RatingTypes\StoreRatingTypeRequest;
use App\Http\Requests\Web\Config\RatingTypes\UpdateRatingTypeRequest;
use App\Models\RatingType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RatingTypeController extends Controller
{
    public function __construct(
        private readonly RatingTypeService $ratingTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RatingType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/RatingTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', RatingType::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RatingType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code', 'max_score'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->ratingTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', RatingType::class);

        return Inertia::render('Config/RatingTypes/Create');
    }

    public function store(StoreRatingTypeRequest $request): RedirectResponse
    {
        $this->ratingTypes->create($request->validated());

        return redirect()
            ->route('config.rating-types.index')
            ->with('success', 'rating_type_created_successfully');
    }

    public function edit(Request $request, RatingType $ratingType): Response
    {
        $this->authorize('update', $ratingType);

        return Inertia::render('Config/RatingTypes/Edit', [
            'ratingType' => $this->ratingTypes->toFormData($ratingType),
            'can' => [
                'delete' => $request->user()?->can('delete', $ratingType) ?? false,
            ],
        ]);
    }

    public function update(UpdateRatingTypeRequest $request, RatingType $ratingType): RedirectResponse
    {
        $this->ratingTypes->update($ratingType, $request->validated());

        return redirect()
            ->route('config.rating-types.index')
            ->with('success', 'rating_type_updated_successfully');
    }

    public function destroy(RatingType $ratingType): RedirectResponse
    {
        $this->authorize('delete', $ratingType);

        $this->ratingTypes->delete($ratingType);

        return redirect()
            ->route('config.rating-types.index')
            ->with('success', 'rating_type_deleted_successfully');
    }
}
