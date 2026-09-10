<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\TechnicianAttendanceConfirmationType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TechnicianAttendanceConfirmationTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_technician_attendance_confirmation_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/technician-attendance-confirmation-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/TechnicianAttendanceConfirmationTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('confirmationTypes'));

        $this->actingAs($admin)
            ->post('/config/technician-attendance-confirmation-types', [
                'name' => 'Whatsapp',
            ])
            ->assertRedirect(route('config.technician-attendance-confirmation-types.index'))
            ->assertSessionHas('success', 'technician_attendance_confirmation_type_created_successfully');

        $type = TechnicianAttendanceConfirmationType::query()->where('name', 'Whatsapp')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/technician-attendance-confirmation-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.name', 'Whatsapp');

        $this->actingAs($admin)
            ->put("/config/technician-attendance-confirmation-types/{$type->id}", [
                'name' => 'WhatsApp',
            ])
            ->assertRedirect(route('config.technician-attendance-confirmation-types.index'))
            ->assertSessionHas('success', 'technician_attendance_confirmation_type_updated_successfully');

        $this->assertDatabaseHas('technician_attendance_confirmation_types', [
            'id' => $type->id,
            'name' => 'WhatsApp',
        ]);

        $this->actingAs($admin)
            ->delete("/config/technician-attendance-confirmation-types/{$type->id}")
            ->assertRedirect(route('config.technician-attendance-confirmation-types.index'))
            ->assertSessionHas('success', 'technician_attendance_confirmation_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_technician_attendance_confirmation_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/technician-attendance-confirmation-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/technician-attendance-confirmation-types/data')
            ->assertForbidden();
    }
}
