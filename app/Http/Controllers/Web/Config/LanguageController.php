<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Languages\Services\LanguageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Languages\StoreLanguageRequest;
use App\Http\Requests\Web\Config\Languages\UpdateLanguageRequest;
use App\Models\Language;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LanguageController extends Controller
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Language::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Languages/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Language::class) ?? false,
                'update' => $request->user()?->can('languages.update') ?? false,
                'delete' => $request->user()?->can('languages.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Language::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->languages->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Language::class);

        return Inertia::render('Config/Languages/Create');
    }

    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        $this->languages->create($request->validated());

        return redirect()
            ->route('config.languages.index')
            ->with('success', 'language_created_successfully');
    }

    public function edit(Request $request, Language $language): Response
    {
        $this->authorize('update', $language);

        return Inertia::render('Config/Languages/Edit', [
            'language' => $this->languages->toFormData($language),
            'can' => [
                'delete' => $request->user()?->can('delete', $language) ?? false,
            ],
        ]);
    }

    public function update(UpdateLanguageRequest $request, Language $language): RedirectResponse
    {
        $this->languages->update($language, $request->validated());

        return redirect()
            ->route('config.languages.index')
            ->with('success', 'language_updated_successfully');
    }

    public function destroy(Language $language): RedirectResponse
    {
        $this->authorize('delete', $language);

        $this->languages->delete($language);

        return redirect()
            ->route('config.languages.index')
            ->with('success', 'language_deleted_successfully');
    }
}
