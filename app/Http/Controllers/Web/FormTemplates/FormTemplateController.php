<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\FormTemplates;

use App\Domain\Config\FormBibles\Services\FormBibleService;
use App\Domain\Forms\Services\FormTemplateService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\FormTemplates\StoreFormTemplateRequest;
use App\Http\Requests\Web\FormTemplates\UpdateFormTemplateRequest;
use App\Models\FormTemplate;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FormTemplateController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly FormTemplateService $templates,
        private readonly FormBibleService $bibles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FormTemplate::class);

        return Inertia::render('FormTemplates/Index', [
            'filters' => [
                'search' => $request->string('search')->trim()->toString(),
                'form_type_id' => $request->string('form_type_id')->trim()->toString(),
                'owner_type' => $request->string('owner_type')->trim()->toString(),
                'sort' => $request->string('sort')->trim()->toString() ?: 'id',
                'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
                'per_page' => (string) ListQuery::perPage([
                    'per_page' => $request->integer('per_page', 12),
                ]),
            ],
            'typeOptions' => $this->templates->typeOptions(),
            'can' => [
                'create' => $request->user()?->can('create', FormTemplate::class) ?? false,
                'update' => $request->user()?->can('form_templates.update') ?? false,
                'delete' => $request->user()?->can('form_templates.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FormTemplate::class);
        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'form_type_id', 'owner_type'],
        );

        return TabulatorResponse::fromPaginator(
            $this->templates->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', FormTemplate::class);
        $owner = $this->activeCompany($request);

        return Inertia::render('FormTemplates/Create', $this->formOptions($owner));
    }

    public function store(StoreFormTemplateRequest $request): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $template = $this->templates->create($owner, $request->validated());

        return redirect()
            ->route('form-templates.edit', $template)
            ->with('success', 'form_template_created_successfully');
    }

    public function edit(Request $request, FormTemplate $formTemplate): Response
    {
        $this->authorize('update', $formTemplate);
        $owner = $this->activeCompany($request);

        return Inertia::render('FormTemplates/Edit', [
            'template' => $this->templates->toFormData($formTemplate),
            ...$this->formOptions($owner),
            'can' => [
                'delete' => $request->user()?->can('delete', $formTemplate) ?? false,
            ],
        ]);
    }

    public function update(UpdateFormTemplateRequest $request, FormTemplate $formTemplate): RedirectResponse
    {
        $this->templates->update($formTemplate, $request->validated());

        return redirect()
            ->route('form-templates.edit', $formTemplate)
            ->with('success', 'form_template_updated_successfully');
    }

    public function destroy(FormTemplate $formTemplate): RedirectResponse
    {
        $this->authorize('delete', $formTemplate);
        $this->templates->delete($formTemplate);

        return redirect()
            ->route('form-templates.index')
            ->with('success', 'form_template_deleted_successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions($owner): array
    {
        return [
            'typeOptions' => $this->templates->typeOptions(),
            'languageOptions' => $this->templates->languageOptions(),
            'workOrderTypeOptions' => $this->templates->workOrderTypeOptions(),
            'brandOptions' => $this->templates->brandOptions(),
            'customerOptions' => $this->templates->customerOptions($owner),
            'establishmentOptions' => $this->templates->establishmentOptions($owner),
            'bibleOptions' => $this->bibles->options(),
        ];
    }
}
