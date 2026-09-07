<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V3\Auth;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_register_endpoint_is_removed(): void
    {
        $this->postJson('/api/v3/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertNotFound();
    }

    public function test_user_can_login_and_access_me(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password1!',
        ]);
        $user->assignRole(RoleEnum::User->value);

        $login = $this->postJson('/api/v3/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password1!',
            'device_name' => 'phpunit',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v3/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'login@example.com');
    }

    public function test_inactive_locked_and_sso_only_users_cannot_use_password_login(): void
    {
        $restrictedAccounts = [
            ['email' => 'inactive@example.com', 'is_active' => false],
            ['email' => 'locked@example.com', 'locked_at' => now()],
            ['email' => 'sso@example.com', 'sso_only' => true],
        ];

        foreach ($restrictedAccounts as $attributes) {
            User::factory()->create([
                ...$attributes,
                'password' => 'Password1!',
            ]);

            $this->postJson('/api/v3/auth/login', [
                'email' => $attributes['email'],
                'password' => 'Password1!',
                'device_name' => 'phpunit',
            ])->assertUnprocessable();

            $this->post('/login', [
                'email' => $attributes['email'],
                'password' => 'Password1!',
            ])->assertSessionHasErrors('email');
        }
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v3/auth/me')->assertUnauthorized();
    }
}
