<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Country;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProvincesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_sync_country_provinces_via_json_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $country = Country::query()->create([
            'name' => 'España',
            'iso_code' => 'ES',
            'timezone_id' => null,
        ]);

        $existing = Province::query()->create([
            'country_id' => $country->id,
            'name' => 'Madrid',
            'code' => '28',
        ]);

        $this->actingAs($admin)
            ->getJson("/config/countries/{$country->id}/provinces")
            ->assertOk()
            ->assertJsonPath('data.0.id', $existing->id)
            ->assertJsonPath('data.0.name', 'Madrid');

        $this->actingAs($admin)
            ->putJson("/config/countries/{$country->id}/provinces", [
                'provinces' => [
                    ['id' => $existing->id, 'name' => 'Comunidad de Madrid', 'code' => '28'],
                    ['id' => null, 'name' => 'Barcelona', 'code' => '08'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('provinces', [
            'id' => $existing->id,
            'name' => 'Comunidad de Madrid',
            'code' => '28',
        ]);
        $this->assertDatabaseHas('provinces', [
            'country_id' => $country->id,
            'name' => 'Barcelona',
            'code' => '08',
        ]);

        $this->actingAs($admin)
            ->putJson("/config/countries/{$country->id}/provinces", [
                'provinces' => [
                    ['id' => null, 'name' => 'Valencia', 'code' => '46'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSoftDeleted($existing);
        $this->assertDatabaseHas('provinces', [
            'country_id' => $country->id,
            'name' => 'Valencia',
            'code' => '46',
        ]);
    }

    public function test_user_without_permission_cannot_view_country_provinces(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $country = Country::query()->create([
            'name' => 'España',
            'iso_code' => 'ES',
            'timezone_id' => null,
        ]);

        $this->actingAs($viewer)
            ->getJson("/config/countries/{$country->id}/provinces")
            ->assertForbidden();
    }
}
