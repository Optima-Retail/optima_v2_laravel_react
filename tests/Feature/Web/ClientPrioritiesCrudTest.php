<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\ClientPriority;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClientPrioritiesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_client_priorities(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/client-priorities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/ClientPriorities/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('clientPriorities'));

        $this->actingAs($admin)
            ->post('/config/client-priorities', [
                'name' => 'Urgency',
                'code' => 'P2',
                'color' => '#FF6B6B',
                'level' => 1,
            ])
            ->assertRedirect(route('config.client-priorities.index'))
            ->assertSessionHas('success', 'client_priority_created_successfully');

        $priority = ClientPriority::query()->where('code', 'P2')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/client-priorities/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $priority->id)
            ->assertJsonPath('data.0.code', 'P2')
            ->assertJsonPath('data.0.level', 1);

        $this->actingAs($admin)
            ->getJson('/config/client-priorities/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Urgency',
                'sort' => [
                    ['field' => 'level', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $priority->id);

        $this->actingAs($admin)
            ->put("/config/client-priorities/{$priority->id}", [
                'name' => 'Critical',
                'code' => 'P2',
                'color' => '#FF6B6B',
                'level' => 1,
            ])
            ->assertRedirect(route('config.client-priorities.index'))
            ->assertSessionHas('success', 'client_priority_updated_successfully');

        $this->assertDatabaseHas('client_priorities', [
            'id' => $priority->id,
            'name' => 'Critical',
            'level' => 1,
        ]);

        $this->actingAs($admin)
            ->delete("/config/client-priorities/{$priority->id}")
            ->assertRedirect(route('config.client-priorities.index'))
            ->assertSessionHas('success', 'client_priority_deleted_successfully');

        $this->assertSoftDeleted($priority);
    }

    public function test_user_without_permission_cannot_view_client_priorities(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/client-priorities')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/client-priorities/data')
            ->assertForbidden();
    }
}
