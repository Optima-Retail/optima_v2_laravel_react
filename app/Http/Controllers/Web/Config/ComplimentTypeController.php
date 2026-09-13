<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\ComplimentTypes\Services\ComplimentTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\ComplimentTypes\StoreComplimentTypeRequest;
use App\Http\Requests\Web\Config\ComplimentTypes\UpdateComplimentTypeRequest;
use App\Models\ComplimentType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ComplimentTypeController extends Controller
{
    public function __construct(
        private readonly ComplimentTypeService $complimentTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ComplimentType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/ComplimentTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', ComplimentType::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ComplimentType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->complimentTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', ComplimentType::class);

        return Inertia::render('Config/ComplimentTypes/Create');
    }

    public function store(StoreComplimentTypeRequest $request): RedirectResponse
    {
        $this->complimentTypes->create($request->validated());

        return redirect()
            ->route('config.compliment-types.index')
            ->with('success', 'compliment_type_created_successfully');
    }

    public function edit(Request $request, ComplimentType $complimentType): Response
    {
        $this->authorize('update', $complimentType);

        return Inertia::render('Config/ComplimentTypes/Edit', [
            'complimentType' => $this->complimentTypes->toFormData($complimentType),
            'can' => [
                'delete' => $request->user()?->can('delete', $complimentType) ?? false,
            ],
        ]);
    }

    public function update(UpdateComplimentTypeRequest $request, ComplimentType $complimentType): RedirectResponse
    {
        $this->complimentTypes->update($complimentType, $request->validated());

        return redirect()
            ->route('config.compliment-types.index')
            ->with('success', 'compliment_type_updated_successfully');
    }

    public function destroy(ComplimentType $complimentType): RedirectResponse
    {
        $this->authorize('delete', $complimentType);

        $this->complimentTypes->delete($complimentType);

        return redirect()
            ->route('config.compliment-types.index')
            ->with('success', 'compliment_type_deleted_successfully');
    }
}
