<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\IndirectCostType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IndirectCostTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_indirect_cost_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/indirect-cost-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/IndirectCostTypes/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/config/indirect-cost-types', [
                'name' => 'Empresa',
                'code' => 'cie',
                'color' => '#FF5733',
            ])
            ->assertRedirect(route('config.indirect-cost-types.index'))
            ->assertSessionHas('success', 'indirect_cost_type_created_successfully');

        $type = IndirectCostType::query()->where('code', 'CIE')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/indirect-cost-types/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.color', '#FF5733');

        $this->actingAs($admin)
            ->put("/config/indirect-cost-types/{$type->id}", [
                'name' => 'Empresa Updated',
                'code' => 'CIE2',
                'color' => '#33FF57',
            ])
            ->assertRedirect(route('config.indirect-cost-types.index'))
            ->assertSessionHas('success', 'indirect_cost_type_updated_successfully');

        $this->assertDatabaseHas('indirect_cost_types', [
            'id' => $type->id,
            'name' => 'Empresa Updated',
            'code' => 'CIE2',
            'color' => '#33FF57',
        ]);

        $this->actingAs($admin)
            ->delete("/config/indirect-cost-types/{$type->id}")
            ->assertRedirect(route('config.indirect-cost-types.index'))
            ->assertSessionHas('success', 'indirect_cost_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_indirect_cost_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/indirect-cost-types')
            ->assertForbidden();
    }
}
