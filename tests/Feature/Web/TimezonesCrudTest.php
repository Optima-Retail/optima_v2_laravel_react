<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Timezone;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TimezonesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_timezones(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/timezones')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Timezones/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('timezones'));

        $this->actingAs($admin)
            ->post('/config/timezones', [
                'name' => 'Europe/Madrid',
                'timezone' => 'Europe/Madrid',
            ])
            ->assertRedirect(route('config.timezones.index'))
            ->assertSessionHas('success', 'timezone_created_successfully');

        $timezone = Timezone::query()->where('timezone', 'Europe/Madrid')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/timezones/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $timezone->id)
            ->assertJsonPath('data.0.timezone', 'Europe/Madrid');

        $this->actingAs($admin)
            ->getJson('/config/timezones/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Europe/Madrid',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $timezone->id);

        $this->actingAs($admin)
            ->put("/config/timezones/{$timezone->id}", [
                'name' => 'Europe/Madrid (Spain)',
                'timezone' => 'Europe/Madrid',
            ])
            ->assertRedirect(route('config.timezones.index'))
            ->assertSessionHas('success', 'timezone_updated_successfully');

        $this->assertDatabaseHas('timezones', [
            'id' => $timezone->id,
            'name' => 'Europe/Madrid (Spain)',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->actingAs($admin)
            ->delete("/config/timezones/{$timezone->id}")
            ->assertRedirect(route('config.timezones.index'))
            ->assertSessionHas('success', 'timezone_deleted_successfully');

        $this->assertSoftDeleted($timezone);
    }

    public function test_user_without_permission_cannot_view_timezones(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/timezones')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/timezones/data')
            ->assertForbidden();
    }
}
