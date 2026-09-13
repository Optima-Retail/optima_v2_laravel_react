<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\CostCenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CostCentersCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_cost_centers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/cost-centers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/CostCenters/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/config/cost-centers', [
                'name' => 'Marketing',
                'code' => 'mkt',
            ])
            ->assertRedirect(route('config.cost-centers.index'))
            ->assertSessionHas('success', 'cost_center_created_successfully');

        $center = CostCenter::query()->where('code', 'MKT')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/cost-centers/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $center->id)
            ->assertJsonPath('data.0.code', 'MKT');

        $this->actingAs($admin)
            ->put("/config/cost-centers/{$center->id}", [
                'name' => 'Marketing Updated',
                'code' => 'MKTG',
            ])
            ->assertRedirect(route('config.cost-centers.index'))
            ->assertSessionHas('success', 'cost_center_updated_successfully');

        $this->assertDatabaseHas('cost_centers', [
            'id' => $center->id,
            'name' => 'Marketing Updated',
            'code' => 'MKTG',
        ]);

        $this->actingAs($admin)
            ->delete("/config/cost-centers/{$center->id}")
            ->assertRedirect(route('config.cost-centers.index'))
            ->assertSessionHas('success', 'cost_center_deleted_successfully');

        $this->assertSoftDeleted($center);
    }

    public function test_user_without_permission_cannot_view_cost_centers(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/cost-centers')
            ->assertForbidden();
    }
}
