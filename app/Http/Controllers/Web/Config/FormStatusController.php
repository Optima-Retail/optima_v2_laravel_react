<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\FormStatuses\Services\FormStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\FormStatuses\StoreFormStatusRequest;
use App\Http\Requests\Web\Config\FormStatuses\UpdateFormStatusRequest;
use App\Models\FormStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FormStatusController extends Controller
{
    public function __construct(
        private readonly FormStatusService $formStatuses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FormStatus::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/FormStatuses/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', FormStatus::class) ?? false,
                'update' => $request->user()?->can('form_statuses.update') ?? false,
                'delete' => $request->user()?->can('form_statuses.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FormStatus::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'next_status_id', 'is_active'],
            defaultSort: 'id',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->formStatuses->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', FormStatus::class);

        return Inertia::render('Config/FormStatuses/Create', [
            'statusOptions' => $this->formStatuses->statusOptions(),
        ]);
    }

    public function store(StoreFormStatusRequest $request): RedirectResponse
    {
        $this->formStatuses->create([
            'name' => $request->string('name')->toString(),
            'next_status_id' => $request->input('next_status_id'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('config.form-statuses.index')
            ->with('success', 'form_status_created_successfully');
    }

    public function edit(Request $request, FormStatus $formStatus): Response
    {
        $this->authorize('update', $formStatus);

        return Inertia::render('Config/FormStatuses/Edit', [
            'formStatus' => $this->formStatuses->toFormData($formStatus),
            'statusOptions' => $this->formStatuses->statusOptions(
                $formStatus->id,
                $formStatus->next_status_id,
            ),
            'can' => [
                'delete' => $request->user()?->can('delete', $formStatus) ?? false,
            ],
        ]);
    }

    public function update(UpdateFormStatusRequest $request, FormStatus $formStatus): RedirectResponse
    {
        $this->formStatuses->update($formStatus, [
            'name' => $request->string('name')->toString(),
            'next_status_id' => $request->input('next_status_id'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('config.form-statuses.index')
            ->with('success', 'form_status_updated_successfully');
    }

    public function destroy(FormStatus $formStatus): RedirectResponse
    {
        $this->authorize('delete', $formStatus);

        $this->formStatuses->delete($formStatus);

        return redirect()
            ->route('config.form-statuses.index')
            ->with('success', 'form_status_deleted_successfully');
    }
}
