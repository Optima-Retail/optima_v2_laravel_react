<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Article;
use App\Models\ArticleClient;
use App\Models\ClientPriority;
use App\Models\ClientRate;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTechnician;
use App\Models\WorkOrderType;
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
        $collaborator = User::factory()->create();
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
                'collaborator_ids' => [$collaborator->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'client_created_successfully');

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $related->id)
            ->firstOrFail();

        $this->assertSame(CompanyRelationshipKind::Customer->value, $relationship->kind->value);
        $this->assertDatabaseHas('company_relationship_collaborators', [
            'company_relationship_id' => $relationship->id,
            'user_id' => $collaborator->id,
        ]);
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
                'collaborator_ids' => [],
            ])
            ->assertRedirect(route('clients.edit', $relationship))
            ->assertSessionHas('success', 'client_updated_successfully');

        $this->assertDatabaseHas('company_relationships', [
            'id' => $relationship->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseMissing('company_relationship_collaborators', [
            'company_relationship_id' => $relationship->id,
            'user_id' => $collaborator->id,
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
                ->where('relationship.collaborator_ids', [])
                ->has('formOptions.priorityOptions')
                ->has('formOptions.userOptions'));

        $this->actingAs($admin)
            ->delete("/clients/{$relationship->id}")
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success', 'client_deleted_successfully');

        $this->assertSoftDeleted($relationship);
    }

    public function test_unlinking_a_client_priority_soft_deletes_its_rates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $related = Company::factory()->create(['name' => 'Rates Client']);
        $priorityA = ClientPriority::query()->create([
            'name' => 'Keep',
            'code' => 'KEEP',
            'level' => 1,
        ]);
        $priorityB = ClientPriority::query()->create([
            'name' => 'Drop',
            'code' => 'DROP',
            'level' => 2,
        ]);
        $workOrderType = WorkOrderType::query()->create([
            'name' => 'Corrective',
            'code' => 'COR',
            'color' => '#112233',
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
            ->assertRedirect();

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $related->id)
            ->firstOrFail();

        $keepRate = ClientRate::query()->create([
            'company_relationship_id' => $relationship->id,
            'client_priority_id' => $priorityA->id,
            'work_order_type_id' => $workOrderType->id,
            'travel_amount' => 10,
            'extra_travel_amount' => 0,
            'labor_amount' => 20,
            'extra_labor_amount' => 0,
            'due_hours' => 4,
            'sla_hours' => 8,
            'is_urgent' => false,
        ]);
        $dropRate = ClientRate::query()->create([
            'company_relationship_id' => $relationship->id,
            'client_priority_id' => $priorityB->id,
            'work_order_type_id' => $workOrderType->id,
            'travel_amount' => 11,
            'extra_travel_amount' => 0,
            'labor_amount' => 21,
            'extra_labor_amount' => 0,
            'due_hours' => 4,
            'sla_hours' => 8,
            'is_urgent' => true,
        ]);

        $this->actingAs($admin)
            ->put("/clients/{$relationship->id}", [
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Customer->value,
                'classification' => 'commercial',
                'status' => 'active',
                'priority_ids' => [$priorityA->id],
            ])
            ->assertRedirect(route('clients.edit', $relationship));

        $this->assertDatabaseHas('client_rates', [
            'id' => $keepRate->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted($dropRate);
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
            ->assertRedirect()
            ->assertSessionHas('success', 'supplier_created_successfully');

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $related->id)
            ->firstOrFail();

        $this->assertSame(CompanyRelationshipKind::Supplier->value, $relationship->kind->value);

        $this->actingAs($admin)
            ->put("/suppliers/{$relationship->id}", [
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Supplier->value,
                'classification' => 'commercial',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('suppliers.edit', $relationship))
            ->assertSessionHas('success', 'supplier_updated_successfully');

        $this->assertDatabaseHas('company_relationships', [
            'id' => $relationship->id,
            'kind' => 'supplier',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->delete("/suppliers/{$relationship->id}")
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success', 'supplier_deleted_successfully');

        $this->assertSoftDeleted($relationship);
    }

    public function test_admin_can_manage_technicians(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $this->actingAs($admin)
            ->get('/technicians')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Technicians/Index')
                ->has('filters')
                ->has('can.create'));

        $related = Company::factory()->create(['name' => 'Technician Co']);

        $this->actingAs($admin)
            ->post('/technicians', [
                'related_mode' => 'existing',
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Technician->value,
                'classification' => 'commercial',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'technician_created_successfully');

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $owner->id)
            ->where('related_company_id', $related->id)
            ->firstOrFail();

        $this->assertSame(CompanyRelationshipKind::Technician->value, $relationship->kind->value);

        $this->actingAs($admin)
            ->put("/technicians/{$relationship->id}", [
                'related_company_id' => $related->id,
                'kind' => CompanyRelationshipKind::Technician->value,
                'classification' => 'commercial',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('technicians.edit', $relationship))
            ->assertSessionHas('success', 'technician_updated_successfully');

        $this->assertDatabaseHas('company_relationships', [
            'id' => $relationship->id,
            'kind' => 'technician',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->delete("/technicians/{$relationship->id}")
            ->assertRedirect(route('technicians.index'))
            ->assertSessionHas('success', 'technician_deleted_successfully');

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

        CompanyRelationship::factory()->create([
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
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $supplier->id)
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->getJson('/suppliers/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'kind' => 'not_a_real_kind',
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1);
    }

    public function test_technicians_data_is_scoped_to_active_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Supplier,
        ]);

        $this->actingAs($admin)
            ->getJson('/technicians/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $technician->id);
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

    public function test_technician_used_in_work_order_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $technicianCompany = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'name' => 'Store 1',
            'code' => 'S1',
        ]);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'owner_company_id' => $owner->id,
            'subject' => 'Assigned job',
            'code' => 'WO-TECH-1',
        ]);

        WorkOrderTechnician::query()->create([
            'work_order_id' => $workOrder->id,
            'company_relationship_id' => $relationship->id,
        ]);

        $this->actingAs($admin)
            ->from(route('technicians.edit', $relationship))
            ->delete("/technicians/{$relationship->id}")
            ->assertRedirect(route('technicians.edit', $relationship))
            ->assertSessionHas('error', 'technician_cannot_be_deleted');

        $this->assertNotSoftDeleted($relationship);

        $this->actingAs($admin)
            ->get("/technicians/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Technicians/Edit')
                ->where('can.delete', false));
    }

    public function test_client_linked_to_article_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $related = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $related->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $article = Article::query()->create([
            'code' => 'CLI-LINK',
            'is_deletable' => true,
        ]);

        ArticleClient::query()->create([
            'article_id' => $article->id,
            'company_relationship_id' => $relationship->id,
            'sale_price' => 12.5,
        ]);

        $this->actingAs($admin)
            ->from(route('clients.edit', $relationship))
            ->delete("/clients/{$relationship->id}")
            ->assertRedirect(route('clients.edit', $relationship))
            ->assertSessionHas('error', 'client_cannot_be_deleted');

        $this->assertNotSoftDeleted($relationship);

        $this->actingAs($admin)
            ->get("/clients/{$relationship->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Edit')
                ->where('can.delete', false));
    }
}
