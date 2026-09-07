<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V3;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class CompaniesApiTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_companies_via_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v3/companies', [
            'name' => 'API Party',
            'kind' => CompanyKind::Party->value,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'API Party');

        $company = Company::query()->where('name', 'API Party')->firstOrFail();

        $this->getJson('/api/v3/companies')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson("/api/v3/companies/{$company->id}", [
            'name' => 'API Party Updated',
            'kind' => CompanyKind::Party->value,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'API Party Updated');

        $this->deleteJson("/api/v3/companies/{$company->id}")
            ->assertOk();

        $this->assertSoftDeleted($company);
    }

    public function test_me_companies_and_switch(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $first = Company::factory()->create(['name' => 'Ledger A']);
        $second = Company::factory()->create(['name' => 'Ledger B']);
        $this->attachToCompany($admin, $first);
        $this->attachToCompany($admin, $second, active: false);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v3/me/companies')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->postJson('/api/v3/me/company/switch', ['company_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('data.id', $second->id);

        $outsider = Company::factory()->create();
        $this->postJson('/api/v3/me/company/switch', ['company_id' => $outsider->id])
            ->assertForbidden();
    }

    public function test_relationships_and_establishments_are_scoped(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $other = Company::factory()->create();
        $brand = $this->makeBrand('API');
        $this->attachToCompany($admin, $owner);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v3/relationships', [
            'related_mode' => 'existing',
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer->value,
            'status' => CompanyRelationshipStatus::Active->value,
            'classification' => CompanyRelationshipClassification::Commercial->value,
            'brand_id' => $brand->id,
        ])->assertCreated();

        $this->getJson('/api/v3/relationships')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v3/establishments', [
            'company_id' => $client->id,
            'name' => 'Client Store',
            'is_active' => true,
        ])->assertCreated();

        Establishment::factory()->create(['company_id' => $other->id, 'name' => 'Secret']);

        $this->getJson('/api/v3/establishments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Client Store');
    }
}
