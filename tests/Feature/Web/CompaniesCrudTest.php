<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyKind;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class CompaniesCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_companies_and_is_attached_as_member(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Companies/Index'));

        $this->actingAs($admin)
            ->post('/companies', [
                'name' => 'Optima Party',
                'tradename' => 'OP',
                'tax_id' => 'B12345678',
                'kind' => CompanyKind::Party->value,
                'is_active' => true,
            ])
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('success', 'company_created_successfully');

        $company = Company::query()->where('tax_id', 'B12345678')->firstOrFail();
        $this->assertTrue($admin->fresh()?->belongsToCompany($company->id));

        $this->actingAs($admin)
            ->put("/companies/{$company->id}", [
                'name' => 'Optima Party SL',
                'tradename' => 'OP',
                'tax_id' => 'B12345678',
                'kind' => CompanyKind::Party->value,
                'is_active' => true,
            ])
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('success', 'company_updated_successfully');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Optima Party SL',
        ]);

        $this->actingAs($admin)
            ->delete("/companies/{$company->id}")
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('success', 'company_deleted_successfully');

        $this->assertSoftDeleted($company);
    }

    public function test_user_belongs_only_to_attached_companies(): void
    {
        $user = User::factory()->create();
        $inside = Company::factory()->create();
        $outside = Company::factory()->create();

        $this->attachToCompany($user, $inside);

        $this->assertTrue($user->belongsToCompany($inside->id));
        $this->assertFalse($user->belongsToCompany($outside->id));
    }

    public function test_company_index_only_lists_memberships(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $inside = Company::factory()->create(['name' => 'Inside Co']);
        Company::factory()->create(['name' => 'Outside Co']);
        $this->attachToCompany($admin, $inside);

        $this->actingAs($admin)
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Companies/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete'));

        $this->actingAs($admin)
            ->getJson('/companies/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $inside->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_companies_data_supports_tabulator_search_sort_filter_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $alpha = Company::factory()->create([
            'name' => 'Alpha Search Co',
            'kind' => CompanyKind::Party->value,
            'tax_id' => 'A11111111',
        ]);
        $beta = Company::factory()->create([
            'name' => 'Beta Other Co',
            'kind' => CompanyKind::Holding->value,
            'tax_id' => 'B22222222',
        ]);
        Company::factory()->create([
            'name' => 'Gamma Holding',
            'kind' => CompanyKind::Holding->value,
        ]);

        $this->attachToCompany($admin, $alpha);
        $this->attachToCompany($admin, $beta);

        $extras = Company::factory()->count(10)->create();
        foreach ($extras as $extra) {
            $this->attachToCompany($admin, $extra);
        }

        $this->actingAs($admin)
            ->getJson('/companies/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Alpha',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $alpha->id);

        $this->actingAs($admin)
            ->getJson('/companies/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'kind' => CompanyKind::Holding->value,
                'sort' => [
                    ['field' => 'name', 'dir' => 'desc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $beta->id);

        // 12 memberships total (alpha, beta + 10 extras) with size=10 → 2 pages
        $this->actingAs($admin)
            ->getJson('/companies/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'sort' => [
                    ['field' => 'id', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('last_row', 12)
            ->assertJsonCount(10, 'data');

        $this->actingAs($admin)
            ->getJson('/companies/data?'.http_build_query([
                'page' => 2,
                'size' => 10,
                'sort' => [
                    ['field' => 'id', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)
            ->getJson('/companies/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'kind' => 'not_a_real_kind',
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 12);
    }

    public function test_companies_data_is_forbidden_without_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->getJson('/companies/data')
            ->assertForbidden();
    }

    public function test_admin_can_assign_and_unlink_company_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $this->attachToCompany($admin, $company);

        $member = User::factory()->create(['name' => 'Member User']);

        $this->actingAs($admin)
            ->post("/companies/{$company->id}/users", ['user_id' => $member->id])
            ->assertRedirect(route('companies.edit', $company))
            ->assertSessionHas('success', 'company_user_assigned_successfully');

        $this->assertTrue($member->fresh()?->belongsToCompany($company->id));

        $this->actingAs($admin)
            ->delete("/companies/{$company->id}/users/{$member->id}")
            ->assertRedirect(route('companies.edit', $company))
            ->assertSessionHas('success', 'company_user_unlinked_successfully');

        $this->assertFalse($member->fresh()?->belongsToCompany($company->id));
    }

    public function test_user_without_permission_cannot_view_companies(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/companies')
            ->assertForbidden();
    }
}
