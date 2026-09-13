<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\TechnicianIncidentStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TechnicianIncidentStatusesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_technician_incident_statuses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/technician-incident-statuses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/TechnicianIncidentStatuses/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('technicianIncidentStatuses'));

        $this->actingAs($admin)
            ->post('/config/technician-incident-statuses', [
                'name' => 'Abierta',
                'color' => '#a9cef0',
                'lifecycle' => 1,
                'is_open' => true,
            ])
            ->assertRedirect(route('config.technician-incident-statuses.index'))
            ->assertSessionHas('success', 'technician_incident_status_created_successfully');

        $status = TechnicianIncidentStatus::query()->where('name', 'Abierta')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/technician-incident-statuses/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $status->id)
            ->assertJsonPath('data.0.lifecycle', 1)
            ->assertJsonPath('data.0.is_open', true);

        $this->actingAs($admin)
            ->put("/config/technician-incident-statuses/{$status->id}", [
                'name' => 'Abierta Updated',
                'color' => '#a9cef0',
                'lifecycle' => 1,
                'is_open' => false,
            ])
            ->assertRedirect(route('config.technician-incident-statuses.index'))
            ->assertSessionHas('success', 'technician_incident_status_updated_successfully');

        $this->assertDatabaseHas('technician_incident_statuses', [
            'id' => $status->id,
            'name' => 'Abierta Updated',
            'is_open' => false,
        ]);

        $this->actingAs($admin)
            ->delete("/config/technician-incident-statuses/{$status->id}")
            ->assertRedirect(route('config.technician-incident-statuses.index'))
            ->assertSessionHas('success', 'technician_incident_status_deleted_successfully');

        $this->assertSoftDeleted($status);
    }

    public function test_user_without_permission_cannot_view_technician_incident_statuses(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/technician-incident-statuses')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/technician-incident-statuses/data')
            ->assertForbidden();
    }
}
