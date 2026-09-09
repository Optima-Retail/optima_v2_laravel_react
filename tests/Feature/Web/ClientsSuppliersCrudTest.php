<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\ClientPriority;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class ClientsSuppliersCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_clients(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $this->actingAs($admin)
            ->get('/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->has('filters')
                ->has('can.create')
                ->missing('clients'));

        $related = Company::factory()->create(['name' => 'Client Co']);
        $priorityA = ClientPriority::query()->create([
            'name' => 'Urgency',
            'code' => 'P2',
            'color' => '#FF6B6B',
            'level' => 1,
        ]);
        $priorityB = ClientPriority::query()->create([
            'name' => 'Low',
            'code' => 'P5',
            'color' => '#95DBA1',
            'level' => 5,
        ]);

        $this->actingAs($admin)
            ->post('/clients', [
                'related_mode' => 'existing',
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Customer->value,
                'classification' => 'commercial',
                'status' => 'active',
                'priority_ids' => [$priorityA->id, $priorityB->id],
            ])
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success', 'client_created_successfully');

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $related->id)
            ->firstOrFail();

        $this->assertSame(CompanyRelationshipKind::Customer->value, $relationship->kind->value);
        $this->assertEqualsCanonicalizing(
            [$priorityA->id, $priorityB->id],
            $related->fresh()->priorities()->pluck('client_priorities.id')->all(),
        );

        $this->actingAs($admin)
            ->put("/clients/{$relationship->id}", [
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Customer->value,
                'classification' => 'commercial',
                'status' => 'inactive',
                'priority_ids' => [$priorityA->id],
            ])
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success', 'client_updated_successfully');

        $this->assertDatabaseHas('company_relationships', [
            'id' => $relationship->id,
            'status' => 'inactive',
        ]);
        $this->assertEqualsCanonicalizing(
            [$priorityA->id],
            $related->fresh()->priorities()->pluck('client_priorities.id')->all(),
        );
        $this->assertSoftDeleted('company_priority', [
            'company_id' => $related->id,
            'client_priority_id' => $priorityB->id,
        ]);

        $this->actingAs($admin)
            ->get("/clients/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Edit')
                ->where('relationship.priority_ids', [$priorityA->id])
                ->has('formOptions.priorityOptions'));

        $this->actingAs($admin)
            ->delete("/clients/{$relationship->id}")
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success', 'client_deleted_successfully');

        $this->assertSoftDeleted($relationship);
    }

    public function test_clients_data_is_scoped_to_active_company_and_supports_search_sort_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $alpha = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => Company::factory()->create(['name' => 'Alpha Search Co'])->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => Company::factory()->create(['name' => 'Beta Co'])->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        // Belongs to a different owner company — must never leak into the scoped list.
        CompanyRelationship::factory()->create([
            'owner_company_id' => $other->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        // A supplier-kind relationship on the same owner — must never appear in /clients/data.
        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);

        $this->actingAs($admin)
            ->getJson('/clients/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'sort' => [
                    ['field' => 'id', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 2)
            ->assertJsonPath('data.0.id', $alpha->id)
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)
            ->getJson('/clients/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Alpha',
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $alpha->id);
    }

    public function test_user_without_permission_cannot_view_clients(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/clients')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/clients/data')
            ->assertForbidden();
    }

    public function test_admin_can_manage_suppliers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $this->actingAs($admin)
            ->get('/suppliers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suppliers/Index')
                ->has('filters')
                ->has('can.create')
                ->missing('suppliers'));

        $related = Company::factory()->create(['name' => 'Supplier Co']);

        $this->actingAs($admin)
            ->post('/suppliers', [
                'related_mode' => 'existing',
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Supplier->value,
                'classification' => 'commercial',
                'status' => 'active',
            ])
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success', 'supplier_created_successfully');

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $related->id)
            ->firstOrFail();

        $this->assertSame(CompanyRelationshipKind::Supplier->value, $relationship->kind->value);

        $this->actingAs($admin)
            ->put("/suppliers/{$relationship->id}", [
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Technician->value,
                'classification' => 'commercial',
                'status' => 'active',
            ])
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success', 'supplier_updated_successfully');

        $this->assertDatabaseHas('company_relationships', [
            'id' => $relationship->id,
            'kind' => 'technician',
        ]);

        $this->actingAs($admin)
            ->delete("/suppliers/{$relationship->id}")
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success', 'supplier_deleted_successfully');

        $this->assertSoftDeleted($relationship);
    }

    public function test_suppliers_data_is_scoped_to_active_company_and_supports_kind_filter_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $supplier = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        // Belongs to a different owner company — must never leak into the scoped list.
        CompanyRelationship::factory()->create([
            'owner_company_id' => $other->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);

        // A customer-kind relationship on the same owner — must never appear in /suppliers/data.
        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $this->actingAs($admin)
            ->getJson('/suppliers/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'sort' => [
                    ['field' => 'id', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 2)
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)
            ->getJson('/suppliers/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'kind' => CompanyRelationshipKind::Technician->value,
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $technician->id);

        $this->actingAs($admin)
            ->getJson('/suppliers/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'kind' => 'not_a_real_kind',
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 2);

        $this->assertNotSame($supplier->id, $technician->id);
    }

    public function test_user_without_permission_cannot_view_suppliers(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/suppliers')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/suppliers/data')
            ->assertForbidden();
    }
}
