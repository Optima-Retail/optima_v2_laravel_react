<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IncidentStatusesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_incident_statuses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/incident-statuses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/IncidentStatuses/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('incidentStatuses'));

        $this->actingAs($admin)
            ->post('/config/incident-statuses', [
                'name' => 'Draft',
                'color' => '#f6eac2',
                'lifecycle' => 1,
                'is_open' => true,
                'excluded_type_ids' => [],
            ])
            ->assertRedirect(route('config.incident-statuses.index'))
            ->assertSessionHas('success', 'incident_status_created_successfully');

        $status = IncidentStatus::query()->where('name', 'Draft')->firstOrFail();

        $type = IncidentType::query()->create([
            'name' => 'Controllers',
            'color' => '#FFFFFF',
            'default_priority_id' => null,
            'origin_selectable' => false,
            'origin_options' => [],
            'origin_required' => false,
            'show_related' => false,
        ]);

        $this->actingAs($admin)
            ->getJson('/config/incident-statuses/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $status->id)
            ->assertJsonPath('data.0.lifecycle', 1)
            ->assertJsonPath('data.0.is_open', true);

        $this->actingAs($admin)
            ->get("/config/incident-statuses/{$status->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/IncidentStatuses/Edit')
                ->where('incidentStatus.id', $status->id)
                ->has('incidentTypeOptions')
                ->where('incidentStatus.excluded_type_ids', []));

        $this->actingAs($admin)
            ->put("/config/incident-statuses/{$status->id}", [
                'name' => 'Draft Updated',
                'color' => '#f6eac2',
                'lifecycle' => 1,
                'is_open' => false,
                'excluded_type_ids' => [$type->id],
            ])
            ->assertRedirect(route('config.incident-statuses.index'))
            ->assertSessionHas('success', 'incident_status_updated_successfully');

        $this->assertDatabaseHas('incident_statuses', [
            'id' => $status->id,
            'name' => 'Draft Updated',
            'is_open' => false,
        ]);

        $this->assertDatabaseHas('incident_status_type_exclusions', [
            'incident_status_id' => $status->id,
            'incident_type_id' => $type->id,
        ]);

        $this->actingAs($admin)
            ->delete("/config/incident-statuses/{$status->id}")
            ->assertRedirect(route('config.incident-statuses.index'))
            ->assertSessionHas('success', 'incident_status_deleted_successfully');

        $this->assertSoftDeleted($status);
        $this->assertDatabaseMissing('incident_status_type_exclusions', [
            'incident_status_id' => $status->id,
        ]);
    }

    public function test_user_without_permission_cannot_view_incident_statuses(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/incident-statuses')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/incident-statuses/data')
            ->assertForbidden();
    }
}
