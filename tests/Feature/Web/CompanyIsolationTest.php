<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class CompanyIsolationTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_suppliers_and_establishments_are_isolated_by_active_company(): void
    {
        $adminA = User::factory()->create();
        $adminB = User::factory()->create();
        $adminA->assignRole(RoleEnum::Admin->value);
        $adminB->assignRole(RoleEnum::Admin->value);

        $companyA = Company::factory()->create(['name' => 'Owner A']);
        $companyB = Company::factory()->create(['name' => 'Owner B']);
        $supplierA = Company::factory()->create(['name' => 'Supplier A']);
        $supplierB = Company::factory()->create(['name' => 'Supplier B']);
        $clientA = Company::factory()->create(['name' => 'Client A']);
        $clientB = Company::factory()->create(['name' => 'Client B']);

        $this->attachToCompany($adminA, $companyA);
        $this->attachToCompany($adminB, $companyB);

        $relA = CompanyRelationship::factory()->create([
            'owner_company_id' => $companyA->id,
            'related_company_id' => $supplierA->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);
        $relB = CompanyRelationship::factory()->create([
            'owner_company_id' => $companyB->id,
            'related_company_id' => $supplierB->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $companyA->id,
            'related_company_id' => $clientA->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);
        CompanyRelationship::factory()->create([
            'owner_company_id' => $companyB->id,
            'related_company_id' => $clientB->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $siteA = Establishment::factory()->create(['company_id' => $clientA->id, 'name' => 'Site A']);
        $siteB = Establishment::factory()->create(['company_id' => $clientB->id, 'name' => 'Site B']);

        $this->actingAs($adminA)
            ->get('/suppliers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Suppliers/Index'));

        $this->actingAs($adminA)
            ->getJson('/suppliers/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $relA->id);

        $this->actingAs($adminA)
            ->get("/suppliers/{$relB->id}/edit")
            ->assertForbidden();

        $this->actingAs($adminA)
            ->get('/establishments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Establishments/Index'));

        $this->actingAs($adminA)
            ->getJson('/establishments/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $siteA->id);

        $this->actingAs($adminA)
            ->get("/establishments/{$siteB->id}/edit")
            ->assertForbidden();

        $this->actingAs($adminB)
            ->getJson('/suppliers/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $relB->id);
    }

    public function test_clients_index_only_shows_customer_kind(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $customer = Company::factory()->create(['name' => 'Client Co']);
        $supplier = Company::factory()->create(['name' => 'Supplier Co']);
        $this->attachToCompany($admin, $owner);

        $client = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $customer->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $supplier->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);

        $this->actingAs($admin)
            ->get('/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Clients/Index'));

        $this->actingAs($admin)
            ->getJson('/clients/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $client->id);
    }

    public function test_customer_and_supplier_can_coexist_for_the_same_pair(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $related = Company::factory()->create();
        $brand = $this->makeBrand('Retail');
        $this->attachToCompany($admin, $owner);

        $this->actingAs($admin)
            ->post('/clients', [
                'related_mode' => 'existing',
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Customer->value,
                'status' => CompanyRelationshipStatus::Active->value,
                'classification' => CompanyRelationshipClassification::Commercial->value,
                'brand_id' => $brand->id,
            ])
            ->assertRedirect(route('clients.index'));

        $this->actingAs($admin)
            ->post('/suppliers', [
                'related_mode' => 'existing',
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Supplier->value,
                'status' => CompanyRelationshipStatus::Active->value,
                'classification' => CompanyRelationshipClassification::Commercial->value,
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertSame(2, CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $related->id)
            ->count());
    }

    public function test_self_relationship_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $this->actingAs($admin)
            ->post('/suppliers', [
                'related_mode' => 'existing',
                'related_company_id' => $owner->id,
                'kind' => CompanyRelationshipKind::Supplier->value,
                'status' => CompanyRelationshipStatus::Active->value,
                'classification' => CompanyRelationshipClassification::Commercial->value,
            ])
            ->assertSessionHasErrors('related_company_id');
    }

    public function test_can_create_client_by_creating_a_new_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $brand = $this->makeBrand('Zara');
        $this->attachToCompany($admin, $owner);

        $this->actingAs($admin)
            ->post('/clients', [
                'related_mode' => 'new',
                'related_company' => [
                    'name' => 'New Client SL',
                    'tax_id' => 'B11223344',
                ],
                'kind' => CompanyRelationshipKind::Customer->value,
                'status' => CompanyRelationshipStatus::Active->value,
                'classification' => CompanyRelationshipClassification::Commercial->value,
                'brand_id' => $brand->id,
            ])
            ->assertRedirect(route('clients.index'));

        $client = Company::query()->where('tax_id', 'B11223344')->firstOrFail();

        $this->assertDatabaseHas('company_relationships', [
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer->value,
        ]);
    }

    public function test_establishments_only_list_client_company_sites(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create(['name' => 'Client Co']);
        $supplier = Company::factory()->create(['name' => 'Supplier Co']);
        $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);
        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $supplier->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);

        $clientSite = Establishment::factory()->create([
            'company_id' => $client->id,
            'name' => 'Client store',
        ]);
        Establishment::factory()->create([
            'company_id' => $supplier->id,
            'name' => 'Supplier warehouse',
        ]);
        Establishment::factory()->create([
            'company_id' => $owner->id,
            'name' => 'Owner HQ',
        ]);

        $this->actingAs($admin)
            ->getJson('/establishments/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $clientSite->id);
    }
}
