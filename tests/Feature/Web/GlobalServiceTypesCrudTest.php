<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\GlobalServiceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GlobalServiceTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_global_service_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/global-service-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/GlobalServiceTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('globalServiceTypes'));

        $this->actingAs($admin)
            ->post('/config/global-service-types', [
                'name' => 'Obras',
                'code' => 'OBRAS',
                'color' => '#fcba03',
            ])
            ->assertRedirect(route('config.global-service-types.index'))
            ->assertSessionHas('success', 'global_service_type_created_successfully');

        $type = GlobalServiceType::query()->where('code', 'OBRAS')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/global-service-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.code', 'OBRAS');

        $this->actingAs($admin)
            ->put("/config/global-service-types/{$type->id}", [
                'name' => 'Obras generales',
                'code' => 'OBRAS',
                'color' => '#fcba03',
            ])
            ->assertRedirect(route('config.global-service-types.index'))
            ->assertSessionHas('success', 'global_service_type_updated_successfully');

        $this->assertDatabaseHas('global_service_types', [
            'id' => $type->id,
            'name' => 'Obras generales',
        ]);

        $this->actingAs($admin)
            ->delete("/config/global-service-types/{$type->id}")
            ->assertRedirect(route('config.global-service-types.index'))
            ->assertSessionHas('success', 'global_service_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_global_service_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/global-service-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/global-service-types/data')
            ->assertForbidden();
    }
}
