<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class BrandClientsAndClientEstablishmentsModalTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_clients_for_brand(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $brand = Brand::query()->create([
            'name' => 'ACME',
            'is_quality_control_contactable' => true,
            'send_debt_reminders' => true,
        ]);

        $owner = Company::factory()->create();
        $client = Company::factory()->create(['name' => 'Client Co']);
        $admin = $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
            'status' => CompanyRelationshipStatus::Active,
            'brand_id' => $brand->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('brands.clients', $brand))
            ->assertOk()
            ->assertJsonPath('data.0.related_company_name', 'Client Co')
            ->assertJsonPath('data.0.status', CompanyRelationshipStatus::Active->value);
    }

    public function test_admin_can_list_establishments_for_client(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create(['name' => 'Site Owner']);
        $admin = $this->attachToCompany($admin, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        Establishment::factory()->create([
            'company_id' => $client->id,
            'name' => 'Main Store',
            'code' => 'S001',
            'city' => 'Madrid',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('clients.establishments', $relationship))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Main Store')
            ->assertJsonPath('data.0.code', 'S001')
            ->assertJsonPath('data.0.city', 'Madrid');
    }
}
