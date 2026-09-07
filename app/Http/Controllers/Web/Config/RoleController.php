<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Roles\Services\RoleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Roles\StoreRoleRequest;
use App\Http\Requests\Web\Config\Roles\UpdateRoleRequest;
use App\Models\Role;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'type' => $request->string('type')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Roles/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Role::class) ?? false,
                'update' => $request->user()?->can('roles.update') ?? false,
                'delete' => $request->user()?->can('roles.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search', 'type'],
        );

        $type = (string) ($filters['type'] ?? '');

        if ($type !== '' && ! in_array($type, ['system', 'custom'], true)) {
            $filters['type'] = '';
        }

        return TabulatorResponse::fromPaginator(
            $this->roles->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        $this->roles->syncDiscoveredPermissions();

        return Inertia::render('Config/Roles/Create', [
            'permissionGroups' => $this->roles->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roles->create([
            'name' => $request->string('name')->toString(),
            'permissions' => $request->validated('permissions') ?? [],
        ]);

        return redirect()
            ->route('config.roles.index')
            ->with('success', 'role_created_successfully');
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->authorize('update', $role);

        $this->roles->syncDiscoveredPermissions();

        return Inertia::render('Config/Roles/Edit', [
            'role' => $this->roles->toFormData($role),
            'permissionGroups' => $this->roles->permissionGroups(),
            'can' => [
                'delete' => $request->user()?->can('delete', $role) ?? false,
            ],
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, [
            'name' => $request->string('name')->toString(),
            'permissions' => $request->validated('permissions') ?? [],
        ]);

        return redirect()
            ->route('config.roles.index')
            ->with('success', 'role_updated_successfully');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $this->roles->delete($role);

        return redirect()
            ->route('config.roles.index')
            ->with('success', 'role_deleted_successfully');
    }
}
