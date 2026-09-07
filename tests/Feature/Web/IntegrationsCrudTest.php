<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Integration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IntegrationsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_integrations(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Integrations/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('integrations'));

        $this->actingAs($admin)
            ->post('/config/integrations', [
                'name' => 'Global Service Channel',
                'code' => 'service-channel-global',
            ])
            ->assertRedirect(route('config.integrations.index'))
            ->assertSessionHas('success', 'integration_created_successfully');

        $integration = Integration::query()->where('code', 'service-channel-global')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/integrations/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $integration->id)
            ->assertJsonPath('data.0.code', 'service-channel-global');

        $this->actingAs($admin)
            ->getJson('/config/integrations/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Global',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $integration->id);

        $this->actingAs($admin)
            ->put("/config/integrations/{$integration->id}", [
                'name' => 'Global Service Channel (updated)',
                'code' => 'service-channel-global',
            ])
            ->assertRedirect(route('config.integrations.index'))
            ->assertSessionHas('success', 'integration_updated_successfully');

        $this->assertDatabaseHas('integrations', [
            'id' => $integration->id,
            'name' => 'Global Service Channel (updated)',
            'code' => 'service-channel-global',
        ]);

        $this->actingAs($admin)
            ->delete("/config/integrations/{$integration->id}")
            ->assertRedirect(route('config.integrations.index'))
            ->assertSessionHas('success', 'integration_deleted_successfully');

        $this->assertSoftDeleted($integration);
    }

    public function test_user_without_permission_cannot_view_integrations(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/integrations')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/integrations/data')
            ->assertForbidden();
    }
}
