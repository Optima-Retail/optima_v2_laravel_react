<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class CompanySwitchTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_member_can_switch_active_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $first = Company::factory()->create(['name' => 'Alpha Co']);
        $second = Company::factory()->create(['name' => 'Beta Co']);
        $this->attachToCompany($admin, $first);
        $this->attachToCompany($admin, $second, active: false);

        $this->actingAs($admin)
            ->post('/me/company/switch', ['company_id' => $second->id])
            ->assertRedirect();

        $this->assertSame($second->id, $admin->fresh()?->active_company_id);
    }

    public function test_non_member_cannot_switch_to_a_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $memberOf = Company::factory()->create();
        $outsider = Company::factory()->create();
        $this->attachToCompany($admin, $memberOf);

        $this->actingAs($admin)
            ->post('/me/company/switch', ['company_id' => $outsider->id])
            ->assertForbidden();

        $this->assertSame($memberOf->id, $admin->fresh()?->active_company_id);
    }

    public function test_scoped_routes_require_membership(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/clients')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/suppliers')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/establishments')
            ->assertForbidden();
    }
}
