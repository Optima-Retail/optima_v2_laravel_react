<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Articles\Services\ArticleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Articles\StoreArticleRequest;
use App\Http\Requests\Web\Config\Articles\UpdateArticleRequest;
use App\Models\Article;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Article::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'code',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Articles/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Article::class) ?? false,
                'update' => $request->user()?->can('articles.update') ?? false,
                'delete' => $request->user()?->can('articles.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Article::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'code', 'is_deletable', 'created_at'],
            defaultSort: 'code',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->articles->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Article::class);

        return Inertia::render('Config/Articles/Create', [
            'languageOptions' => $this->articles->languageOptions(),
            'clientOptions' => $this->articles->clientOptions(),
        ]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $this->articles->create($request->validated());

        return redirect()
            ->route('config.articles.index')
            ->with('success', 'article_created_successfully');
    }

    public function edit(Request $request, Article $article): Response
    {
        $this->authorize('update', $article);

        return Inertia::render('Config/Articles/Edit', [
            'article' => $this->articles->toFormData($article),
            'languageOptions' => $this->articles->languageOptions(),
            'clientOptions' => $this->articles->clientOptions(),
            'can' => [
                'delete' => ($request->user()?->can('delete', $article) ?? false) && $article->is_deletable,
            ],
        ]);
    }

    public function update(UpdateArticleRequest $request, Article $article): RedirectResponse
    {
        $this->articles->update($article, $request->validated());

        return redirect()
            ->route('config.articles.index')
            ->with('success', 'article_updated_successfully');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $this->articles->delete($article);

        return redirect()
            ->route('config.articles.index')
            ->with('success', 'article_deleted_successfully');
    }
}
