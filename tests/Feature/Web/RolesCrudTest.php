<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RolesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_roles_and_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Roles/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('roles'));

        $this->actingAs($admin)
            ->post('/config/roles', [
                'name' => 'support',
                'permissions' => ['users.view', 'roles.view'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'role_created_successfully');

        $role = Role::query()->where('name', 'support')->firstOrFail();

        $this->assertTrue($role->hasPermissionTo('users.view'));
        $this->assertTrue($role->hasPermissionTo('roles.view'));

        $this->actingAs($admin)
            ->getJson('/config/roles/data?'.http_build_query(['search' => 'support']))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $role->id)
            ->assertJsonPath('data.0.name', 'support');

        $this->actingAs($admin)
            ->getJson('/config/roles/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'support',
                'type' => 'custom',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $role->id);

        $this->actingAs($admin)
            ->put("/config/roles/{$role->id}", [
                'name' => 'support',
                'permissions' => ['users.view'],
            ])
            ->assertRedirect(route('config.roles.edit', $role))
            ->assertSessionHas('success', 'role_updated_successfully');

        $this->assertFalse($role->fresh()->hasPermissionTo('roles.view'));

        $this->actingAs($admin)
            ->delete("/config/roles/{$role->id}")
            ->assertRedirect(route('config.roles.index'))
            ->assertSessionHas('success', 'role_deleted_successfully');

        $this->assertSoftDeleted($role);
        $this->assertNull(Role::query()->find($role->id));
        $this->assertNotNull(Role::withTrashed()->find($role->id));
    }

    public function test_admin_role_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $adminRole = Role::findByName(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->delete("/config/roles/{$adminRole->id}")
            ->assertForbidden();
    }

    public function test_admin_role_is_viewable_but_not_editable_and_keeps_all_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $adminRole = Role::findByName(RoleEnum::Admin->value);
        $allPermissions = $adminRole->permissions->pluck('name')->sort()->values()->all();

        $this->assertNotEmpty($allPermissions);

        $this->actingAs($admin)
            ->get("/config/roles/{$adminRole->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Roles/Edit')
                ->where('role.name', RoleEnum::Admin->value)
                ->where('role.is_system', true)
                ->where('can.update', false)
                ->where('can.delete', false)
                ->has('role.permissions', count($allPermissions))
                ->has('permissionGroups'));

        $this->actingAs($admin)
            ->put("/config/roles/{$adminRole->id}", [
                'name' => 'admin',
                'permissions' => ['users.view'],
            ])
            ->assertForbidden();

        $adminRole->refresh();
        $this->assertSame(
            $allPermissions,
            $adminRole->permissions->pluck('name')->sort()->values()->all(),
        );
        $this->assertTrue($adminRole->hasPermissionTo('roles.update'));
    }

    public function test_user_without_roles_permission_cannot_view_roles(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/roles')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/roles/data')
            ->assertForbidden();
    }
}
