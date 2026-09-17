<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Forms;

use App\Domain\Forms\Services\FormPdfService;
use App\Domain\Forms\Services\FormService;
use App\Domain\Forms\Services\FormTemplateService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Forms\StoreFormFieldFileRequest;
use App\Http\Requests\Web\Forms\StoreFormRequest;
use App\Http\Requests\Web\Forms\UpdateFormRequest;
use App\Models\Form;
use App\Models\FormField;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FormController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly FormService $forms,
        private readonly FormTemplateService $templates,
        private readonly FormPdfService $formPdf,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Form::class);

        return Inertia::render('Forms/Index', [
            'filters' => [
                'search' => $request->string('search')->trim()->toString(),
                'form_type_id' => $request->string('form_type_id')->trim()->toString(),
                'form_status_id' => $request->string('form_status_id')->trim()->toString(),
                'subject_type' => $request->string('subject_type')->trim()->toString(),
                'sort' => $request->string('sort')->trim()->toString() ?: 'id',
                'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
                'per_page' => (string) ListQuery::perPage([
                    'per_page' => $request->integer('per_page', 25),
                ]),
            ],
            'typeOptions' => $this->forms->typeOptions(),
            'statusOptions' => $this->forms->statusOptions(),
            'can' => [
                'create' => $request->user()?->can('create', Form::class) ?? false,
                'update' => $request->user()?->can('forms.update') ?? false,
                'delete' => $request->user()?->can('forms.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Form::class);
        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'created_at', 'occurred_on'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'form_type_id', 'form_status_id', 'subject_type'],
        );

        return TabulatorResponse::fromPaginator(
            $this->forms->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Form::class);
        $owner = $this->activeCompany($request);

        return Inertia::render('Forms/Create', [
            'typeOptions' => $this->forms->typeOptions(),
            'statusOptions' => $this->forms->statusOptions(),
            'templateOptions' => $this->templates->options($owner),
            'workOrderOptions' => $this->forms->workOrderOptions($owner),
            'technicianOptions' => $this->forms->technicianOptions($owner),
            'languageOptions' => $this->templates->languageOptions(),
        ]);
    }

    public function templateSuggestions(Request $request): JsonResponse
    {
        $this->authorize('create', Form::class);
        $owner = $this->activeCompany($request);

        $workOrder = $this->forms->findAccessibleWorkOrder($owner, $request->integer('work_order_id'));

        abort_if($workOrder === null, 404);

        $formTypeId = $request->filled('form_type_id') ? $request->integer('form_type_id') : null;

        return response()->json([
            'data' => $this->templates->resolveForWorkOrder($owner, $workOrder, $formTypeId),
        ]);
    }

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $form = $this->forms->create($user, $request->validated());

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'form_created_successfully');
    }

    public function edit(Request $request, Form $form): Response
    {
        $this->authorize('update', $form);
        $owner = $this->activeCompany($request);

        return Inertia::render('Forms/Edit', [
            'form' => $this->forms->toFormData($form),
            'typeOptions' => $this->forms->typeOptions(),
            'statusOptions' => $this->forms->statusOptions(
                $form->form_status_id !== null ? (int) $form->form_status_id : null,
            ),
            'templateOptions' => $this->templates->options($owner),
            'workOrderOptions' => $this->forms->workOrderOptions($owner),
            'technicianOptions' => $this->forms->technicianOptions($owner),
            'languageOptions' => $this->templates->languageOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $form) ?? false,
            ],
        ]);
    }

    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
        $this->forms->update($form, $request->validated());

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'form_updated_successfully');
    }

    public function advance(Form $form): RedirectResponse
    {
        $this->authorize('update', $form);
        $this->forms->advanceStatus($form);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'form_status_advanced_successfully');
    }

    public function uploadFieldFile(StoreFormFieldFileRequest $request, Form $form, FormField $field): JsonResponse
    {
        $field = $this->forms->storeFieldFile($form, $field, (string) $request->validated('target'), $request->file('file'));

        return response()->json([
            'value' => $field->value,
            'payload' => $field->payload,
            'url' => route('forms.fields.file', ['form' => $form, 'field' => $field, 'target' => $request->validated('target')]),
        ]);
    }

    public function showFieldFile(Request $request, Form $form, FormField $field): StreamedResponse
    {
        $this->authorize('update', $form);

        return $this->forms->streamFieldFile($form, $field, $request->string('target', 'value')->toString());
    }

    public function downloadPdf(Form $form): HttpResponse
    {
        $this->authorize('view', $form);

        return $this->formPdf->stream($form);
    }

    public function showPublic(string $public_id): Response
    {
        $form = $this->forms->findPublicByPublicId($public_id);

        return Inertia::render('Forms/Public', [
            'form' => $this->forms->toPublicData($form),
        ]);
    }

    public function destroy(Form $form): RedirectResponse
    {
        $this->authorize('delete', $form);
        $this->forms->delete($form);

        return redirect()
            ->route('forms.index')
            ->with('success', 'form_deleted_successfully');
    }
}
