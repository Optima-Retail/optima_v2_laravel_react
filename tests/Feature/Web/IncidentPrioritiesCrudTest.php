<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\IncidentPriority;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IncidentPrioritiesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_incident_priorities(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/incident-priorities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/IncidentPriorities/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('incidentPriorities'));

        $this->actingAs($admin)
            ->post('/config/incident-priorities', [
                'name' => 'Alto impacto',
                'color' => '#FF9999',
                'resolution_time_hours' => 4,
            ])
            ->assertRedirect(route('config.incident-priorities.index'))
            ->assertSessionHas('success', 'incident_priority_created_successfully');

        $priority = IncidentPriority::query()->where('name', 'Alto impacto')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/incident-priorities/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $priority->id)
            ->assertJsonPath('data.0.resolution_time_hours', 4);

        $this->actingAs($admin)
            ->getJson('/config/incident-priorities/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Alto',
                'sort' => [
                    ['field' => 'id', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $priority->id);

        $this->actingAs($admin)
            ->put("/config/incident-priorities/{$priority->id}", [
                'name' => 'Critical impacto',
                'color' => '#FF9999',
                'resolution_time_hours' => 2,
            ])
            ->assertRedirect(route('config.incident-priorities.index'))
            ->assertSessionHas('success', 'incident_priority_updated_successfully');

        $this->assertDatabaseHas('incident_priorities', [
            'id' => $priority->id,
            'name' => 'Critical impacto',
            'resolution_time_hours' => 2,
        ]);

        $this->actingAs($admin)
            ->delete("/config/incident-priorities/{$priority->id}")
            ->assertRedirect(route('config.incident-priorities.index'))
            ->assertSessionHas('success', 'incident_priority_deleted_successfully');

        $this->assertSoftDeleted($priority);
    }

    public function test_user_without_permission_cannot_view_incident_priorities(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/incident-priorities')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/incident-priorities/data')
            ->assertForbidden();
    }
}
