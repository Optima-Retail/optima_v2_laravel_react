<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Series\Services\SeriesService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Series\StoreSeriesRequest;
use App\Http\Requests\Web\Config\Series\UpdateSeriesRequest;
use App\Models\Series;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SeriesController extends Controller
{
    public function __construct(
        private readonly SeriesService $series,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Series::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'key',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Series/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Series::class) ?? false,
                'update' => $request->user()?->can('series.update') ?? false,
                'delete' => $request->user()?->can('series.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Series::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'key', 'color', 'is_selectable', 'credit_note_series_id'],
            defaultSort: 'key',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->series->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Series::class);

        return Inertia::render('Config/Series/Create', [
            'seriesOptions' => $this->series->seriesOptions(),
        ]);
    }

    public function store(StoreSeriesRequest $request): RedirectResponse
    {
        $this->series->create($request->validated());

        return redirect()
            ->route('config.series.index')
            ->with('success', 'series_created_successfully');
    }

    public function edit(Request $request, Series $series): Response
    {
        $this->authorize('update', $series);

        return Inertia::render('Config/Series/Edit', [
            'seriesItem' => $this->series->toFormData($series),
            'seriesOptions' => $this->series->seriesOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $series) ?? false,
            ],
        ]);
    }

    public function update(UpdateSeriesRequest $request, Series $series): RedirectResponse
    {
        $this->series->update($series, $request->validated());

        return redirect()
            ->route('config.series.index')
            ->with('success', 'series_updated_successfully');
    }

    public function destroy(Series $series): RedirectResponse
    {
        $this->authorize('delete', $series);

        $this->series->delete($series);

        return redirect()
            ->route('config.series.index')
            ->with('success', 'series_deleted_successfully');
    }
}
