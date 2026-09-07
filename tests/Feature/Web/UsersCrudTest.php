<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class UsersCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_is_the_index_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_register_routes_are_removed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_admin_can_manage_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Users/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('users'));

        $this->actingAs($admin)
            ->post('/config/users', [
                'name' => 'New Operator',
                'email' => 'operator@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'roles' => [RoleEnum::User->value, RoleEnum::Admin->value],
            ])
            ->assertRedirect(route('config.users.index'))
            ->assertSessionHas('success', 'user_created_successfully');

        $this->assertDatabaseHas('users', ['email' => 'operator@example.com']);

        $created = User::query()->where('email', 'operator@example.com')->firstOrFail();
        $this->assertTrue($created->hasAllRoles([RoleEnum::User->value, RoleEnum::Admin->value]));

        $this->actingAs($admin)
            ->getJson('/config/users/data?'.http_build_query([
                'search' => $admin->name,
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $admin->id);

        $this->actingAs($admin)
            ->getJson('/config/users/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'New Operator',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $created->id);

        $this->actingAs($admin)
            ->getJson('/config/users/data?'.http_build_query([
                'role' => RoleEnum::Admin->value,
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 2);

        $this->actingAs($admin)
            ->put("/config/users/{$created->id}", [
                'name' => 'Updated Operator',
                'email' => 'operator@example.com',
                'roles' => [RoleEnum::User->value],
            ])
            ->assertRedirect(route('config.users.index'))
            ->assertSessionHas('success', 'user_updated_successfully');

        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'name' => 'Updated Operator',
        ]);
        $this->assertTrue($created->fresh()->hasRole(RoleEnum::User->value));
        $this->assertFalse($created->fresh()->hasRole(RoleEnum::Admin->value));

        $this->actingAs($admin)
            ->delete("/config/users/{$created->id}")
            ->assertRedirect(route('config.users.index'))
            ->assertSessionHas('success', 'user_deleted_successfully');

        $this->assertSoftDeleted('users', ['id' => $created->id]);
        $this->assertNull(User::query()->find($created->id));
        $this->assertNotNull(User::withTrashed()->find($created->id));
    }

    public function test_user_without_manage_permissions_cannot_create_users(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/users')
            ->assertOk();

        $this->actingAs($viewer)
            ->get('/config/users/create')
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->delete("/config/users/{$admin->id}")
            ->assertForbidden();
    }
}
