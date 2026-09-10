<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class VehiclesCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_vehicles(): void
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

        $this->actingAs($admin)
            ->get('/config/vehicles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Vehicles/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->get('/config/vehicles/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Vehicles/Create')
                ->has('technicianOptions'));

        $this->actingAs($admin)
            ->post('/config/vehicles', [
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'license_plate' => '1234ABC',
                'company_relationship_id' => $relationship->id,
            ])
            ->assertRedirect(route('config.vehicles.index'))
            ->assertSessionHas('success', 'vehicle_created_successfully');

        $vehicle = Vehicle::query()->where('license_plate', '1234ABC')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/vehicles/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $vehicle->id)
            ->assertJsonPath('data.0.brand', 'Toyota')
            ->assertJsonPath('data.0.license_plate', '1234ABC');

        $this->actingAs($admin)
            ->put("/config/vehicles/{$vehicle->id}", [
                'brand' => 'Toyota',
                'model' => 'Yaris',
                'license_plate' => '1234ABC',
                'company_relationship_id' => $relationship->id,
            ])
            ->assertRedirect(route('config.vehicles.index'))
            ->assertSessionHas('success', 'vehicle_updated_successfully');

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'model' => 'Yaris',
        ]);

        $this->actingAs($admin)
            ->delete("/config/vehicles/{$vehicle->id}")
            ->assertRedirect(route('config.vehicles.index'))
            ->assertSessionHas('success', 'vehicle_deleted_successfully');

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_admin_can_sync_technician_vehicles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $technicianCompany = Company::factory()->create(['name' => 'Field Tech']);
        $admin = $this->attachToCompany($admin, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        $existing = Vehicle::query()->create([
            'brand' => 'Old',
            'model' => 'Van',
            'license_plate' => 'OLD1',
            'company_relationship_id' => $relationship->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('suppliers.vehicles', $relationship))
            ->assertOk()
            ->assertJsonPath('data.0.id', $existing->id)
            ->assertJsonPath('data.0.license_plate', 'OLD1');

        $this->actingAs($admin)
            ->putJson(route('suppliers.vehicles.sync', $relationship), [
                'vehicles' => [
                    [
                        'id' => $existing->id,
                        'brand' => 'Ford',
                        'model' => 'Transit',
                        'license_plate' => 'NEW1',
                    ],
                    [
                        'id' => null,
                        'brand' => 'Seat',
                        'model' => 'Leon',
                        'license_plate' => 'NEW2',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('vehicles', [
            'id' => $existing->id,
            'brand' => 'Ford',
            'license_plate' => 'NEW1',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('vehicles', [
            'company_relationship_id' => $relationship->id,
            'license_plate' => 'NEW2',
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->putJson(route('suppliers.vehicles.sync', $relationship), [
                'vehicles' => [
                    [
                        'id' => null,
                        'brand' => 'Only',
                        'model' => 'One',
                        'license_plate' => 'ONLY1',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.license_plate', 'ONLY1');

        $this->assertSoftDeleted('vehicles', ['id' => $existing->id]);
    }

    public function test_supplier_relationship_cannot_sync_vehicles(): void
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
            ->getJson(route('suppliers.vehicles', $relationship))
            ->assertNotFound();

        $this->actingAs($admin)
            ->putJson(route('suppliers.vehicles.sync', $relationship), [
                'vehicles' => [],
            ])
            ->assertNotFound();
    }
}
