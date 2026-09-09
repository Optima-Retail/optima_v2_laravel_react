<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\IncidentPrioritySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IncidentTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(IncidentPrioritySeeder::class);
    }

    public function test_admin_can_manage_incident_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/incident-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/IncidentTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('incidentTypes'));

        $this->actingAs($admin)
            ->get('/config/incident-types/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/IncidentTypes/Create')
                ->has('incidentPriorityOptions', 2));

        $this->actingAs($admin)
            ->post('/config/incident-types', [
                'name' => 'CX',
                'color' => '#FFFFFF',
                'default_priority_id' => 2,
            ])
            ->assertRedirect(route('config.incident-types.index'))
            ->assertSessionHas('success', 'incident_type_created_successfully');

        $type = IncidentType::query()->where('name', 'CX')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/incident-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.default_priority_name', 'Medio impacto')
            ->assertJsonPath('data.0.default_priority_color', '#EFF19A');

        $this->actingAs($admin)
            ->getJson('/config/incident-types/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'CX',
                'sort' => [
                    ['field' => 'id', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id);

        $this->actingAs($admin)
            ->put("/config/incident-types/{$type->id}", [
                'name' => 'CX Updated',
                'color' => '#FFFFFF',
                'default_priority_id' => 1,
            ])
            ->assertRedirect(route('config.incident-types.index'))
            ->assertSessionHas('success', 'incident_type_updated_successfully');

        $this->assertDatabaseHas('incident_types', [
            'id' => $type->id,
            'name' => 'CX Updated',
            'default_priority_id' => 1,
        ]);

        $this->actingAs($admin)
            ->delete("/config/incident-types/{$type->id}")
            ->assertRedirect(route('config.incident-types.index'))
            ->assertSessionHas('success', 'incident_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_incident_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/incident-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/incident-types/data')
            ->assertForbidden();
    }
}
