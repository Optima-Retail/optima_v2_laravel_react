<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\GlobalServiceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class TechnicianGlobalServiceTypesCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_sync_technician_global_service_types_via_supplier_update(): void
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

        $global = GlobalServiceType::query()->create([
            'name' => 'Clima',
            'code' => 'CLIMA',
            'color' => '#fcba03',
        ]);

        $this->actingAs($admin)
            ->get("/suppliers/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suppliers/Edit')
                ->where('relationship.global_service_type_ids', [])
                ->has('formOptions.globalServiceTypeOptions'));

        $this->actingAs($admin)
            ->put("/suppliers/{$relationship->id}", [
                'related_company_id' => $technicianCompany->id,
                'kind' => CompanyRelationshipKind::Technician->value,
                'status' => CompanyRelationshipStatus::Active->value,
                'classification' => 'commercial',
                'service_type_ids' => [],
                'global_service_type_ids' => [$global->id],
                'collaborator_ids' => [],
                'priority_ids' => [],
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('technician_global_service_types', [
            'company_relationship_id' => $relationship->id,
            'global_service_type_id' => $global->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->get("/suppliers/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relationship.global_service_type_ids', [$global->id]));
    }
}
