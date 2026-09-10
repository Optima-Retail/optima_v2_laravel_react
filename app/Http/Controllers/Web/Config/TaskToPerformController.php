<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\TasksToPerform\Services\TaskToPerformService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\TasksToPerform\StoreTaskToPerformRequest;
use App\Http\Requests\Web\Config\TasksToPerform\UpdateTaskToPerformRequest;
use App\Models\TaskToPerform;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TaskToPerformController extends Controller
{
    public function __construct(
        private readonly TaskToPerformService $tasks,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TaskToPerform::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/TasksToPerform/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', TaskToPerform::class) ?? false,
                'update' => $request->user()?->can('tasks_to_perform.update') ?? false,
                'delete' => $request->user()?->can('tasks_to_perform.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaskToPerform::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'title', 'document_type', 'document_id', 'is_completed'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->tasks->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', TaskToPerform::class);

        return Inertia::render('Config/TasksToPerform/Create', [
            'documentTypeOptions' => $this->tasks->documentTypeOptions(),
        ]);
    }

    public function store(StoreTaskToPerformRequest $request): RedirectResponse
    {
        $this->tasks->create($request->validated());

        return redirect()
            ->route('config.tasks-to-perform.index')
            ->with('success', 'task_to_perform_created_successfully');
    }

    public function edit(Request $request, TaskToPerform $taskToPerform): Response
    {
        $this->authorize('update', $taskToPerform);

        return Inertia::render('Config/TasksToPerform/Edit', [
            'taskToPerform' => $this->tasks->toFormData($taskToPerform),
            'documentTypeOptions' => $this->tasks->documentTypeOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $taskToPerform) ?? false,
            ],
        ]);
    }

    public function update(UpdateTaskToPerformRequest $request, TaskToPerform $taskToPerform): RedirectResponse
    {
        $this->tasks->update($taskToPerform, $request->validated());

        return redirect()
            ->route('config.tasks-to-perform.index')
            ->with('success', 'task_to_perform_updated_successfully');
    }

    public function destroy(TaskToPerform $taskToPerform): RedirectResponse
    {
        $this->authorize('delete', $taskToPerform);

        $this->tasks->delete($taskToPerform);

        return redirect()
            ->route('config.tasks-to-perform.index')
            ->with('success', 'task_to_perform_deleted_successfully');
    }
}
