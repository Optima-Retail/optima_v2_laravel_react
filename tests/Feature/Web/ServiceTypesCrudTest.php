<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ServiceTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_service_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/service-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/ServiceTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('serviceTypes'));

        $this->actingAs($admin)
            ->post('/config/service-types', [
                'name' => 'Electricity',
                'code' => 'ELEC',
                'color' => '#5e5126',
            ])
            ->assertRedirect(route('config.service-types.index'))
            ->assertSessionHas('success', 'service_type_created_successfully');

        $type = ServiceType::query()->where('code', 'ELEC')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/service-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.code', 'ELEC');

        $this->actingAs($admin)
            ->put("/config/service-types/{$type->id}", [
                'name' => 'Electrical',
                'code' => 'ELEC',
                'color' => '#5e5126',
            ])
            ->assertRedirect(route('config.service-types.index'))
            ->assertSessionHas('success', 'service_type_updated_successfully');

        $this->assertDatabaseHas('service_types', [
            'id' => $type->id,
            'name' => 'Electrical',
        ]);

        $this->actingAs($admin)
            ->delete("/config/service-types/{$type->id}")
            ->assertRedirect(route('config.service-types.index'))
            ->assertSessionHas('success', 'service_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_service_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/service-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/service-types/data')
            ->assertForbidden();
    }
}
