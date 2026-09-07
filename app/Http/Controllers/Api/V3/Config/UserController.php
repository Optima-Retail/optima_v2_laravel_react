<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3\Config;

use App\Domain\Config\Users\Services\UserService;
use App\Http\Controllers\Api\V3\BaseApiController;
use App\Http\Requests\Api\V3\Config\Users\StoreUserRequest;
use App\Http\Requests\Api\V3\Config\Users\UpdateUserRequest;
use App\Http\Resources\Api\V3\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends BaseApiController
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->users->paginate(
            filters: [
                'search' => $request->string('search')->trim()->toString(),
                'role' => $request->string('role')->trim()->toString(),
            ],
            perPage: $request->integer('per_page', 15),
        );

        return $this->success(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'roles' => $request->validated('roles'),
        ]);

        return $this->created(
            new UserResource($user),
            'user_created_successfully'
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->success(new UserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->users->update($user, [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->filled('password') ? $request->string('password')->toString() : null,
            'roles' => $request->validated('roles'),
        ]);

        return $this->success(
            new UserResource($user),
            'user_updated_successfully'
        );
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return $this->success(null, 'user_deleted_successfully');
    }
}
