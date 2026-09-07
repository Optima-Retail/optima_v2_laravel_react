<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Users\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Users\StoreUserRequest;
use App\Http\Requests\Web\Config\Users\UpdateUserRequest;
use App\Models\User;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'role' => $request->string('role')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Users/Index', [
            'filters' => $filters,
            'roleOptions' => $this->users->roleOptions(),
            'can' => [
                'create' => $request->user()?->can('create', User::class) ?? false,
                'update' => $request->user()?->can('users.update') ?? false,
                'delete' => $request->user()?->can('users.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'email'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search', 'role'],
        );

        $role = (string) ($filters['role'] ?? '');

        if ($role !== '' && ! in_array($role, $this->users->roleOptions(), true)) {
            $filters['role'] = '';
        }

        return TabulatorResponse::fromPaginator(
            $this->users->paginateForWeb($filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Config/Users/Create', [
            'roleOptions' => $this->users->roleOptions(),
            'formOptions' => $this->users->formOptions(actor: $request->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->users->create($request->validated());

        return redirect()
            ->route('config.users.index')
            ->with('success', 'user_created_successfully');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Config/Users/Edit', [
            'user' => $this->users->toFormData($user),
            'roleOptions' => $this->users->roleOptions(),
            'formOptions' => $this->users->formOptions($user, $request->user()),
            'can' => [
                'delete' => $request->user()?->can('delete', $user) ?? false,
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, [
            ...$request->validated(),
            '_actor' => $request->user(),
        ]);

        return redirect()
            ->route('config.users.index')
            ->with('success', 'user_updated_successfully');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return redirect()
            ->route('config.users.index')
            ->with('success', 'user_deleted_successfully');
    }
}
