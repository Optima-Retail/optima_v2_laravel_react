<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\EstablishmentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EstablishmentTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_establishment_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/establishment-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/EstablishmentTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->missing('establishmentTypes'));

        $this->actingAs($admin)
            ->post('/config/establishment-types', [
                'name' => 'Tienda',
                'code' => 'store',
                'health_and_safety_delay_days' => 5,
            ])
            ->assertRedirect(route('config.establishment-types.index'))
            ->assertSessionHas('success', 'establishment_type_created_successfully');

        $establishmentType = EstablishmentType::query()->where('code', 'store')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/establishment-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $establishmentType->id)
            ->assertJsonPath('data.0.code', 'store');

        $this->actingAs($admin)
            ->put("/config/establishment-types/{$establishmentType->id}", [
                'name' => 'Store',
                'code' => 'store',
                'health_and_safety_delay_days' => 5,
            ])
            ->assertRedirect(route('config.establishment-types.index'))
            ->assertSessionHas('success', 'establishment_type_updated_successfully');

        $this->assertDatabaseHas('establishment_types', [
            'id' => $establishmentType->id,
            'name' => 'Store',
            'code' => 'store',
            'health_and_safety_delay_days' => 5,
        ]);

        $this->actingAs($admin)
            ->delete("/config/establishment-types/{$establishmentType->id}")
            ->assertRedirect(route('config.establishment-types.index'))
            ->assertSessionHas('success', 'establishment_type_deleted_successfully');

        $this->assertSoftDeleted($establishmentType);
    }

    public function test_user_without_permission_cannot_view_establishment_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/establishment-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/establishment-types/data')
            ->assertForbidden();
    }
}
