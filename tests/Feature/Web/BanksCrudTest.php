<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Bank;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BanksCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_banks(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        Country::query()->create([
            'id' => 1,
            'name' => 'España',
            'iso_code' => 'ES',
        ]);

        $this->actingAs($admin)
            ->get('/config/banks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Banks/Index')
                ->has('filters')
                ->has('can.create')
                ->missing('banks'));

        $this->actingAs($admin)
            ->post('/config/banks', [
                'name' => 'BBVA',
                'legal_name' => 'Banco Bilbao Vizcaya Argentaria',
                'swift_bic' => 'BBVAESMMXXX',
                'national_bank_code' => '0182',
                'lei' => '',
                'supervisor_code' => '',
                'website' => 'https://www.bbva.es',
                'is_active' => true,
            ])
            ->assertRedirect(route('config.banks.index'))
            ->assertSessionHas('success', 'bank_created_successfully');

        $bank = Bank::query()->where('name', 'BBVA')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/banks/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $bank->id)
            ->assertJsonPath('data.0.swift_bic', 'BBVAESMMXXX');

        $this->actingAs($admin)
            ->getJson('/config/banks/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'BBVA',
                'status' => 'active',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $bank->id);

        $this->assertDatabaseHas('banks', [
            'id' => $bank->id,
            'name' => 'BBVA',
            'swift_bic' => 'BBVAESMMXXX',
            'country_id' => 1,
            'is_active' => 1,
        ]);

        $this->actingAs($admin)
            ->put("/config/banks/{$bank->id}", [
                'name' => 'BBVA Spain',
                'legal_name' => 'Banco Bilbao Vizcaya Argentaria',
                'swift_bic' => 'BBVAESMMXXX',
                'national_bank_code' => '0182',
                'lei' => '',
                'supervisor_code' => '',
                'website' => 'https://www.bbva.es',
                'is_active' => true,
            ])
            ->assertRedirect(route('config.banks.index'))
            ->assertSessionHas('success', 'bank_updated_successfully');

        $this->assertDatabaseHas('banks', [
            'id' => $bank->id,
            'name' => 'BBVA Spain',
        ]);

        $this->actingAs($admin)
            ->delete("/config/banks/{$bank->id}")
            ->assertRedirect(route('config.banks.index'))
            ->assertSessionHas('success', 'bank_deleted_successfully');

        $this->assertSoftDeleted($bank);
    }

    public function test_user_without_permission_cannot_view_banks(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/banks')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/banks/data')
            ->assertForbidden();
    }
}
