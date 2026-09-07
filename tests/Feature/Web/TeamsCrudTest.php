<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TeamsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_teams(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/teams')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Teams/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('teams'));

        $this->actingAs($admin)
            ->post('/config/teams', [
                'code' => 'ADM',
                'name' => 'Administration',
            ])
            ->assertRedirect(route('config.teams.index'))
            ->assertSessionHas('success', 'team_created_successfully');

        $team = Team::query()->where('code', 'ADM')->firstOrFail();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'code' => 'ADM',
            'name' => 'Administration',
            'manager_id' => null,
            'controller_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/config/teams/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $team->id)
            ->assertJsonPath('data.0.code', 'ADM');

        $this->actingAs($admin)
            ->getJson('/config/teams/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Admin',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $team->id);

        $this->actingAs($admin)
            ->put("/config/teams/{$team->id}", [
                'code' => 'ADM',
                'name' => 'Admin Office',
            ])
            ->assertRedirect(route('config.teams.index'))
            ->assertSessionHas('success', 'team_updated_successfully');

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Admin Office',
        ]);

        $this->actingAs($admin)
            ->delete("/config/teams/{$team->id}")
            ->assertRedirect(route('config.teams.index'))
            ->assertSessionHas('success', 'team_deleted_successfully');

        $this->assertSoftDeleted($team);
    }

    public function test_user_without_permission_cannot_view_teams(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/teams')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/teams/data')
            ->assertForbidden();
    }
}
