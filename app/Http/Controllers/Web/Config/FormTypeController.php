<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\FormTypes\Services\FormTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\FormTypes\StoreFormTypeRequest;
use App\Http\Requests\Web\Config\FormTypes\UpdateFormTypeRequest;
use App\Models\FormType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FormTypeController extends Controller
{
    public function __construct(
        private readonly FormTypeService $formTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FormType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/FormTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', FormType::class) ?? false,
                'update' => $request->user()?->can('form_types.update') ?? false,
                'delete' => $request->user()?->can('form_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FormType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->formTypes->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', FormType::class);

        return Inertia::render('Config/FormTypes/Create');
    }

    public function store(StoreFormTypeRequest $request): RedirectResponse
    {
        $this->formTypes->create([
            'name' => $request->string('name')->toString(),
        ]);

        return redirect()
            ->route('config.form-types.index')
            ->with('success', 'form_type_created_successfully');
    }

    public function edit(Request $request, FormType $formType): Response
    {
        $this->authorize('update', $formType);

        return Inertia::render('Config/FormTypes/Edit', [
            'formType' => $this->formTypes->toFormData($formType),
            'can' => [
                'delete' => $request->user()?->can('delete', $formType) ?? false,
            ],
        ]);
    }

    public function update(UpdateFormTypeRequest $request, FormType $formType): RedirectResponse
    {
        $this->formTypes->update($formType, [
            'name' => $request->string('name')->toString(),
        ]);

        return redirect()
            ->route('config.form-types.index')
            ->with('success', 'form_type_updated_successfully');
    }

    public function destroy(FormType $formType): RedirectResponse
    {
        $this->authorize('delete', $formType);

        $this->formTypes->delete($formType);

        return redirect()
            ->route('config.form-types.index')
            ->with('success', 'form_type_deleted_successfully');
    }
}
