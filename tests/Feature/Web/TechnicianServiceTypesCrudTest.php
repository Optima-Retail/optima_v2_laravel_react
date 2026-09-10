<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class TechnicianServiceTypesCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_sync_technician_service_types_on_supplier(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $technicianCompany = Company::factory()->create(['name' => 'Tech Co']);
        $admin = $this->attachToCompany($admin, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        $electric = ServiceType::query()->create([
            'name' => 'Electricidad',
            'code' => 'ELEC',
            'color' => '#5e5126',
        ]);
        $plumbing = ServiceType::query()->create([
            'name' => 'Fontaneria',
            'code' => 'FONT',
            'color' => '#be4d25',
        ]);

        $this->actingAs($admin)
            ->getJson("/suppliers/{$relationship->id}/service-types")
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonFragment(['id' => $electric->id, 'label' => 'ELEC — Electricidad']);

        $this->actingAs($admin)
            ->putJson("/suppliers/{$relationship->id}/service-types", [
                'service_type_ids' => [$electric->id, $plumbing->id],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['service_type_id' => $electric->id])
            ->assertJsonFragment(['service_type_id' => $plumbing->id]);

        $this->assertDatabaseHas('technician_service_types', [
            'company_relationship_id' => $relationship->id,
            'service_type_id' => $electric->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('technician_service_types', [
            'company_relationship_id' => $relationship->id,
            'service_type_id' => $plumbing->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->putJson("/suppliers/{$relationship->id}/service-types", [
                'service_type_ids' => [$plumbing->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service_type_id', $plumbing->id);

        $this->assertSoftDeleted('technician_service_types', [
            'company_relationship_id' => $relationship->id,
            'service_type_id' => $electric->id,
        ]);

        $this->actingAs($admin)
            ->getJson("/suppliers/{$relationship->id}/service-types")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service_type_id', $plumbing->id);
    }

    public function test_admin_can_sync_technician_service_types_via_supplier_update(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $technicianCompany = Company::factory()->create(['name' => 'Tech Co']);
        $admin = $this->attachToCompany($admin, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        $electric = ServiceType::query()->create([
            'name' => 'Electricidad',
            'code' => 'ELEC',
            'color' => '#5e5126',
        ]);

        $this->actingAs($admin)
            ->get("/suppliers/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suppliers/Edit')
                ->where('relationship.service_type_ids', [])
                ->has('formOptions.serviceTypeOptions'));

        $payload = [
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician->value,
            'status' => CompanyRelationshipStatus::Active->value,
            'classification' => 'commercial',
            'service_type_ids' => [$electric->id],
            'collaborator_ids' => [],
            'priority_ids' => [],
        ];

        $this->actingAs($admin)
            ->put("/suppliers/{$relationship->id}", $payload)
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('technician_service_types', [
            'company_relationship_id' => $relationship->id,
            'service_type_id' => $electric->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->get("/suppliers/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relationship.service_type_ids', [$electric->id]));
    }

    public function test_config_technician_service_types_routes_are_gone(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/technician-service-types')
            ->assertNotFound();
    }

    public function test_user_without_permission_cannot_view_technician_service_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $owner = Company::factory()->create();
        $technicianCompany = Company::factory()->create();
        $viewer = $this->attachToCompany($viewer, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        $this->actingAs($viewer)
            ->getJson("/suppliers/{$relationship->id}/service-types")
            ->assertForbidden();
    }

    public function test_non_technician_supplier_cannot_manage_service_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $supplierCompany = Company::factory()->create();
        $admin = $this->attachToCompany($admin, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $supplierCompany->id,
            'kind' => CompanyRelationshipKind::Supplier,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        $this->actingAs($admin)
            ->getJson("/suppliers/{$relationship->id}/service-types")
            ->assertNotFound();
    }
}
