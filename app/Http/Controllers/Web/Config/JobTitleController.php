<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\JobTitles\Services\JobTitleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\JobTitles\StoreJobTitleRequest;
use App\Http\Requests\Web\Config\JobTitles\UpdateJobTitleRequest;
use App\Models\JobTitle;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class JobTitleController extends Controller
{
    public function __construct(
        private readonly JobTitleService $jobTitles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JobTitle::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/JobTitles/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', JobTitle::class) ?? false,
                'update' => $request->user()?->can('job_titles.update') ?? false,
                'delete' => $request->user()?->can('job_titles.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JobTitle::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->jobTitles->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', JobTitle::class);

        return Inertia::render('Config/JobTitles/Create');
    }

    public function store(StoreJobTitleRequest $request): RedirectResponse
    {
        $this->jobTitles->create($request->validated());

        return redirect()
            ->route('config.job-titles.index')
            ->with('success', 'job_title_created_successfully');
    }

    public function edit(Request $request, JobTitle $jobTitle): Response
    {
        $this->authorize('update', $jobTitle);

        return Inertia::render('Config/JobTitles/Edit', [
            'jobTitle' => $this->jobTitles->toFormData($jobTitle),
            'can' => [
                'delete' => $request->user()?->can('delete', $jobTitle) ?? false,
            ],
        ]);
    }

    public function update(UpdateJobTitleRequest $request, JobTitle $jobTitle): RedirectResponse
    {
        $this->jobTitles->update($jobTitle, $request->validated());

        return redirect()
            ->route('config.job-titles.index')
            ->with('success', 'job_title_updated_successfully');
    }

    public function destroy(JobTitle $jobTitle): RedirectResponse
    {
        $this->authorize('delete', $jobTitle);

        $this->jobTitles->delete($jobTitle);

        return redirect()
            ->route('config.job-titles.index')
            ->with('success', 'job_title_deleted_successfully');
    }
}
