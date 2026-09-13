<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\FormBibles\Services\FormBibleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\FormBibles\StoreFormBibleRequest;
use App\Http\Requests\Web\Config\FormBibles\UpdateFormBibleRequest;
use App\Models\FormBible;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FormBibleController extends Controller
{
    public function __construct(
        private readonly FormBibleService $formBibles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FormBible::class);

        return Inertia::render('Config/FormBibles/Index', [
            'filters' => [
                'search' => $request->string('search')->trim()->toString(),
                'sort' => $request->string('sort')->trim()->toString() ?: 'name',
                'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
                'per_page' => (string) ListQuery::perPage([
                    'per_page' => $request->integer('per_page', 12),
                ]),
            ],
            'can' => [
                'create' => $request->user()?->can('create', FormBible::class) ?? false,
                'update' => $request->user()?->can('form_bibles.update') ?? false,
                'delete' => $request->user()?->can('form_bibles.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FormBible::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->formBibles->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', FormBible::class);

        return Inertia::render('Config/FormBibles/Create');
    }

    public function store(StoreFormBibleRequest $request): RedirectResponse
    {
        $this->formBibles->create(['name' => $request->string('name')->toString()]);

        return redirect()
            ->route('config.form-bibles.index')
            ->with('success', 'form_bible_created_successfully');
    }

    public function edit(Request $request, FormBible $formBible): Response
    {
        $this->authorize('update', $formBible);

        return Inertia::render('Config/FormBibles/Edit', [
            'formBible' => $this->formBibles->toFormData($formBible),
            'can' => [
                'delete' => $request->user()?->can('delete', $formBible) ?? false,
            ],
        ]);
    }

    public function update(UpdateFormBibleRequest $request, FormBible $formBible): RedirectResponse
    {
        $this->formBibles->update($formBible, ['name' => $request->string('name')->toString()]);

        return redirect()
            ->route('config.form-bibles.index')
            ->with('success', 'form_bible_updated_successfully');
    }

    public function destroy(FormBible $formBible): RedirectResponse
    {
        $this->authorize('delete', $formBible);
        $this->formBibles->delete($formBible);

        return redirect()
            ->route('config.form-bibles.index')
            ->with('success', 'form_bible_deleted_successfully');
    }
}
