<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Delegation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DelegationsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_delegations(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/delegations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Delegations/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('delegations'));

        $this->actingAs($admin)
            ->post('/config/delegations', [
                'name' => 'Madrid Central',
                'tax_id' => 'ESB12345678',
                'cost_includes_vat' => false,
                'recovers_vat' => true,
            ])
            ->assertRedirect(route('config.delegations.index'))
            ->assertSessionHas('success', 'delegation_created_successfully');

        $delegation = Delegation::query()->where('name', 'Madrid Central')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/delegations/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $delegation->id)
            ->assertJsonPath('data.0.name', 'Madrid Central');

        $this->actingAs($admin)
            ->getJson('/config/delegations/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Madrid',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $delegation->id);

        $this->actingAs($admin)
            ->put("/config/delegations/{$delegation->id}", [
                'name' => 'Madrid HQ',
                'tax_id' => 'ESB12345678',
                'cost_includes_vat' => true,
                'recovers_vat' => true,
            ])
            ->assertRedirect(route('config.delegations.index'))
            ->assertSessionHas('success', 'delegation_updated_successfully');

        $this->assertDatabaseHas('delegations', [
            'id' => $delegation->id,
            'name' => 'Madrid HQ',
            'tax_id' => 'ESB12345678',
            'cost_includes_vat' => 1,
            'recovers_vat' => 1,
        ]);

        $this->actingAs($admin)
            ->delete("/config/delegations/{$delegation->id}")
            ->assertRedirect(route('config.delegations.index'))
            ->assertSessionHas('success', 'delegation_deleted_successfully');

        $this->assertSoftDeleted($delegation);
    }

    public function test_user_without_permission_cannot_view_delegations(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/delegations')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/delegations/data')
            ->assertForbidden();
    }
}
