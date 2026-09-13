<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\TechnicianIncidentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TechnicianIncidentTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_technician_incident_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/technician-incident-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/TechnicianIncidentTypes/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/config/technician-incident-types', [
                'name' => 'Feedback',
                'due_days' => 1,
                'send_mail_to_technician' => false,
            ])
            ->assertRedirect(route('config.technician-incident-types.index'))
            ->assertSessionHas('success', 'technician_incident_type_created_successfully');

        $type = TechnicianIncidentType::query()->where('name', 'Feedback')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/technician-incident-types/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.due_days', 1)
            ->assertJsonPath('data.0.send_mail_to_technician', false);

        $this->actingAs($admin)
            ->put("/config/technician-incident-types/{$type->id}", [
                'name' => 'Feedback updated',
                'due_days' => 3,
                'send_mail_to_technician' => true,
            ])
            ->assertRedirect(route('config.technician-incident-types.index'))
            ->assertSessionHas('success', 'technician_incident_type_updated_successfully');

        $this->assertDatabaseHas('technician_incident_types', [
            'id' => $type->id,
            'name' => 'Feedback updated',
            'due_days' => 3,
            'send_mail_to_technician' => true,
        ]);

        $this->actingAs($admin)
            ->delete("/config/technician-incident-types/{$type->id}")
            ->assertRedirect(route('config.technician-incident-types.index'))
            ->assertSessionHas('success', 'technician_incident_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_technician_incident_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/technician-incident-types')
            ->assertForbidden();
    }
}
