<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Teams\Services\TeamService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Teams\StoreTeamRequest;
use App\Http\Requests\Web\Config\Teams\UpdateTeamRequest;
use App\Models\Team;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeamController extends Controller
{
    public function __construct(
        private readonly TeamService $teams,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Teams/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Team::class) ?? false,
                'update' => $request->user()?->can('teams.update') ?? false,
                'delete' => $request->user()?->can('teams.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Team::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'code', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->teams->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Team::class);

        return Inertia::render('Config/Teams/Create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $this->teams->create($request->validated());

        return redirect()
            ->route('config.teams.index')
            ->with('success', 'team_created_successfully');
    }

    public function edit(Request $request, Team $team): Response
    {
        $this->authorize('update', $team);

        return Inertia::render('Config/Teams/Edit', [
            'team' => $this->teams->toFormData($team),
            'can' => [
                'delete' => $request->user()?->can('delete', $team) ?? false,
            ],
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $this->teams->update($team, $request->validated());

        return redirect()
            ->route('config.teams.index')
            ->with('success', 'team_updated_successfully');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $this->teams->delete($team);

        return redirect()
            ->route('config.teams.index')
            ->with('success', 'team_deleted_successfully');
    }
}
