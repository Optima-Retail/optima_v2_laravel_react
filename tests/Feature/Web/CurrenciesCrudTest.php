<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CurrenciesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_currencies(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/currencies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Currencies/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('currencies'));

        $this->actingAs($admin)
            ->post('/config/currencies', [
                'name' => 'Euro',
                'code' => 'EUR',
            ])
            ->assertRedirect(route('config.currencies.index'))
            ->assertSessionHas('success', 'currency_created_successfully');

        $currency = Currency::query()->where('code', 'EUR')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/currencies/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $currency->id)
            ->assertJsonPath('data.0.code', 'EUR');

        $this->actingAs($admin)
            ->getJson('/config/currencies/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Euro',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $currency->id);

        $this->actingAs($admin)
            ->put("/config/currencies/{$currency->id}", [
                'name' => 'Euro (€)',
                'code' => 'EUR',
            ])
            ->assertRedirect(route('config.currencies.index'))
            ->assertSessionHas('success', 'currency_updated_successfully');

        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'name' => 'Euro (€)',
            'code' => 'EUR',
        ]);

        $this->actingAs($admin)
            ->delete("/config/currencies/{$currency->id}")
            ->assertRedirect(route('config.currencies.index'))
            ->assertSessionHas('success', 'currency_deleted_successfully');

        $this->assertSoftDeleted($currency);
    }

    public function test_user_without_permission_cannot_view_currencies(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/currencies')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/currencies/data')
            ->assertForbidden();
    }
}
