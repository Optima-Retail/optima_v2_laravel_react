<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Country;
use App\Models\Timezone;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CountriesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_countries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $timezone = Timezone::query()->create([
            'name' => 'Europe/Madrid',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->actingAs($admin)
            ->get('/config/countries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Countries/Index')
                ->has('filters')
                ->has('can.create')
                ->missing('countries'));

        $this->actingAs($admin)
            ->post('/config/countries', [
                'name' => 'España',
                'iso_code' => 'ES',
                'timezone_id' => $timezone->id,
            ])
            ->assertRedirect(route('config.countries.index'))
            ->assertSessionHas('success', 'country_created_successfully');

        $country = Country::query()->where('iso_code', 'ES')->firstOrFail();

        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'name' => 'España',
            'iso_code' => 'ES',
            'timezone_id' => $timezone->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/config/countries/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $country->id)
            ->assertJsonPath('data.0.iso_code', 'ES');

        $this->actingAs($admin)
            ->getJson('/config/countries/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'España',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $country->id);

        $this->actingAs($admin)
            ->put("/config/countries/{$country->id}", [
                'name' => 'Spain',
                'iso_code' => 'ES',
                'timezone_id' => $timezone->id,
            ])
            ->assertRedirect(route('config.countries.index'))
            ->assertSessionHas('success', 'country_updated_successfully');

        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'name' => 'Spain',
            'timezone_id' => $timezone->id,
        ]);

        $this->actingAs($admin)
            ->delete("/config/countries/{$country->id}")
            ->assertRedirect(route('config.countries.index'))
            ->assertSessionHas('success', 'country_deleted_successfully');

        $this->assertSoftDeleted($country);
    }

    public function test_user_without_permission_cannot_view_countries(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/countries')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/countries/data')
            ->assertForbidden();
    }
}
