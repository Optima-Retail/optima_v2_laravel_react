<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3\Auth;

use App\Http\Controllers\Api\V3\BaseApiController;
use App\Http\Requests\Api\V3\Auth\LoginRequest;
use App\Http\Resources\Api\V3\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends BaseApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->where('is_active', true)
            ->where('sso_only', false)
            ->whereNull('locked_at')
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken(
            $request->string('device_name')->toString() ?: 'api'
        )->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles')),
        ], 'Authenticated successfully');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new UserResource($user->load('roles')),
            'current_user'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return $this->success(null, 'logged_out_successfully');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->delete();

        return $this->success(null, 'logged_out_from_all_devices');
    }
}
