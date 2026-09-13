<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Delegation;
use App\Models\TechnicianAlternativeDelegation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class TechnicianAlternativeDelegationsCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_sync_alternative_delegations_via_technician_update(): void
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

        $madrid = Delegation::factory()->create(['name' => 'Madrid']);
        $barcelona = Delegation::factory()->create(['name' => 'Barcelona']);

        $this->actingAs($admin)
            ->get("/technicians/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Technicians/Edit')
                ->where('relationship.alternative_delegation_ids', [])
                ->has('formOptions.delegationOptions'));

        $this->actingAs($admin)
            ->put("/technicians/{$relationship->id}", [
                'related_company_id' => $technicianCompany->id,
                'kind' => CompanyRelationshipKind::Technician->value,
                'status' => CompanyRelationshipStatus::Active->value,
                'classification' => 'commercial',
                'alternative_delegation_ids' => [$madrid->id, $barcelona->id],
                'collaborator_ids' => [],
                'priority_ids' => [],
            ])
            ->assertRedirect(route('technicians.index'));

        $this->assertDatabaseHas('technician_alternative_delegations', [
            'company_relationship_id' => $relationship->id,
            'delegation_id' => $madrid->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('technician_alternative_delegations', [
            'company_relationship_id' => $relationship->id,
            'delegation_id' => $barcelona->id,
            'deleted_at' => null,
        ]);
        $this->assertSame(
            2,
            TechnicianAlternativeDelegation::query()->where('company_relationship_id', $relationship->id)->count(),
        );

        $this->actingAs($admin)
            ->get("/technicians/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relationship.alternative_delegation_ids', [$madrid->id, $barcelona->id]));

        $this->actingAs($admin)
            ->put("/technicians/{$relationship->id}", [
                'related_company_id' => $technicianCompany->id,
                'kind' => CompanyRelationshipKind::Technician->value,
                'status' => CompanyRelationshipStatus::Active->value,
                'classification' => 'commercial',
                'alternative_delegation_ids' => [$madrid->id],
                'collaborator_ids' => [],
                'priority_ids' => [],
            ])
            ->assertRedirect(route('technicians.index'));

        $this->assertSoftDeleted('technician_alternative_delegations', [
            'company_relationship_id' => $relationship->id,
            'delegation_id' => $barcelona->id,
        ]);
        $this->assertSame(
            1,
            TechnicianAlternativeDelegation::query()->where('company_relationship_id', $relationship->id)->count(),
        );
    }
}
