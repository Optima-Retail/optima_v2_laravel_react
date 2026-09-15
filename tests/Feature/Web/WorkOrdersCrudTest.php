<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Establishment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderStatusTransition;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class WorkOrdersCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_update_and_delete_work_orders(): void
    {
        [$admin, $establishment, , , $received] = $this->seedContext();

        $this->actingAs($admin)
            ->get('/work-orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkOrders/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/work-orders', [
                'subject' => 'Install unit',
                'status_id' => $received->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'OT-100',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'work_order_created_successfully');

        $workOrder = WorkOrder::query()->where('subject', 'Install unit')->firstOrFail();

        $this->assertTrue($workOrder->isConfirmedWorkOrder());
        $this->assertSame($received->id, $workOrder->status_id);
        $this->assertNotNull($workOrder->confirmed_at);
        $this->assertSame(
            (int) $admin->fresh()?->active_company_id,
            (int) $workOrder->owner_company_id,
        );

        $this->actingAs($admin)
            ->getJson('/work-orders/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $workOrder->id)
            ->assertJsonPath('data.0.stage', 'work_order');

        $this->actingAs($admin)
            ->get("/work-orders/{$workOrder->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkOrders/Edit')
                ->where('workOrder.subject', 'Install unit')
                ->where('workOrder.status_is_open', true)
                ->where('fields_locked', false)
                ->where('can.update_closed', true));

        $this->actingAs($admin)
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Install unit updated',
                'status_id' => $received->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => true,
                'code' => 'OT-100',
            ])
            ->assertRedirect(route('work-orders.edit', $workOrder))
            ->assertSessionHas('success', 'work_order_updated_successfully');

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'subject' => 'Install unit updated',
            'is_urgent' => true,
            'stage' => 'work_order',
        ]);

        $this->actingAs($admin)
            ->delete("/work-orders/{$workOrder->id}")
            ->assertRedirect(route('work-orders.index'))
            ->assertSessionHas('success', 'work_order_deleted_successfully');

        $this->assertSoftDeleted($workOrder);
    }

    public function test_rejecting_a_work_order_clones_a_new_estimate(): void
    {
        [$admin, $establishment, $pending, , $received, $rejected] = $this->seedContext();

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $received->id,
            'subject' => 'Job to reject',
            'code' => 'WO-200',
        ]);

        $response = $this->actingAs($admin)
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Job to reject',
                'status_id' => $rejected->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'WO-200',
            ])
            ->assertSessionHas('success', 'work_order_estimate_created_successfully');

        $workOrder->refresh();
        $this->assertSame($rejected->id, $workOrder->status_id);
        $this->assertTrue($workOrder->isConfirmedWorkOrder());
        $this->assertNotNull($workOrder->closed_at);
        $this->assertNotSame(12, $rejected->id);

        $clone = WorkOrder::query()
            ->where('source_work_order_id', $workOrder->id)
            ->firstOrFail();

        $this->assertTrue($clone->isEstimate());
        $this->assertSame($pending->id, $clone->status_id);
        $this->assertStringContainsString('viene de WO-200', (string) $clone->subject);
        $this->assertNotSame($workOrder->id, $clone->id);

        $response->assertRedirect(route('estimates.edit', $clone));
    }

    public function test_closed_work_order_rejects_field_changes_without_update_closed(): void
    {
        [$admin, $establishment, , , $received, , $company] = $this->seedContext();
        $closed = $this->makeStatus(808, WorkOrderStage::WorkOrder, 'Finalizada', 5, false);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $closed->id,
            'subject' => 'Locked job',
            'code' => 'OT-LOCK',
        ]);

        $editor = User::factory()->create();
        $editor->givePermissionTo(['work_orders.view', 'work_orders.update', 'work_orders.create']);
        $this->attachToCompany($editor, $company);

        $this->actingAs($editor)
            ->from("/work-orders/{$workOrder->id}/edit")
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Hacked subject',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'OT-LOCK',
            ])
            ->assertRedirect("/work-orders/{$workOrder->id}/edit")
            ->assertSessionHasErrors('subject');

        $workOrder->refresh();
        $this->assertSame('Locked job', $workOrder->subject);

        $this->actingAs($editor)
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Locked job',
                'status_id' => $received->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'OT-LOCK',
            ])
            ->assertRedirect(route('work-orders.edit', $workOrder));

        $workOrder->refresh();
        $this->assertSame($received->id, $workOrder->status_id);
        $this->assertSame('Locked job', $workOrder->subject);

        $this->actingAs($admin)
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Admin can edit closed',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'OT-LOCK',
            ])
            ->assertRedirect(route('work-orders.edit', $workOrder));

        $workOrder->refresh();
        $this->assertSame('Admin can edit closed', $workOrder->subject);
    }

    public function test_forbidden_work_order_status_transition_is_rejected(): void
    {
        [$admin, $establishment, , , $received] = $this->seedContext();
        $progress = $this->makeStatus(909, WorkOrderStage::WorkOrder, 'En progreso', 3, true);
        $other = $this->makeStatus(910, WorkOrderStage::WorkOrder, 'Other OT', 4, true);

        WorkOrderStatusTransition::query()->create([
            'from_status_id' => $received->id,
            'to_status_id' => $progress->id,
            'requires_confirmation' => false,
            'requires_justification' => false,
        ]);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $received->id,
            'subject' => 'Transition OT',
            'code' => 'OT-TR',
        ]);

        $this->actingAs($admin)
            ->from("/work-orders/{$workOrder->id}/edit")
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Transition OT',
                'status_id' => $other->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'OT-TR',
            ])
            ->assertRedirect("/work-orders/{$workOrder->id}/edit")
            ->assertSessionHasErrors('status_id');

        $this->actingAs($admin)
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Transition OT',
                'status_id' => $progress->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'OT-TR',
            ])
            ->assertRedirect(route('work-orders.edit', $workOrder));

        $workOrder->refresh();
        $this->assertSame($progress->id, $workOrder->status_id);
    }

    public function test_work_order_routes_reject_estimates(): void
    {
        [$admin, $establishment, $pending] = $this->seedContext();

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Still estimate',
        ]);

        $this->actingAs($admin)
            ->get("/work-orders/{$estimate->id}/edit")
            ->assertNotFound();
    }

    public function test_user_without_permission_cannot_view_work_orders(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $company = Company::factory()->create();
        $this->attachToCompany($viewer, $company);

        $this->actingAs($viewer)
            ->get('/work-orders')
            ->assertForbidden();
    }

    public function test_work_orders_are_isolated_by_active_company(): void
    {
        [$admin, $establishmentA, , , $received, , $companyA] = $this->seedContext();

        $companyB = Company::factory()->create(['name' => 'Company B']);
        $this->attachToCompany($admin, $companyB, active: false);

        $clientB = Company::factory()->create(['name' => 'Client B']);
        CompanyRelationship::factory()->create([
            'owner_company_id' => $companyB->id,
            'related_company_id' => $clientB->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $establishmentB = Establishment::query()->create([
            'company_id' => $clientB->id,
            'name' => 'Store B',
            'code' => 'SB',
        ]);

        $this->actingAs($admin)
            ->post('/work-orders', [
                'subject' => 'Owned by A',
                'status_id' => $received->id,
                'establishment_id' => $establishmentA->id,
                'is_urgent' => false,
                'code' => 'OT-A-1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'work_order_created_successfully');

        $workOrderA = WorkOrder::query()->where('subject', 'Owned by A')->firstOrFail();
        $this->assertSame($companyA->id, (int) $workOrderA->owner_company_id);

        $admin->forceFill(['active_company_id' => $companyB->id])->save();

        $this->actingAs($admin)
            ->post('/work-orders', [
                'subject' => 'Owned by B',
                'status_id' => $received->id,
                'establishment_id' => $establishmentB->id,
                'is_urgent' => false,
                'code' => 'OT-B-1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'work_order_created_successfully');

        $workOrderB = WorkOrder::query()->where('subject', 'Owned by B')->firstOrFail();
        $this->assertSame($companyB->id, (int) $workOrderB->owner_company_id);

        $this->actingAs($admin)
            ->getJson('/work-orders/data')
            ->assertOk()
            ->assertJsonFragment(['id' => $workOrderB->id])
            ->assertJsonMissing(['id' => $workOrderA->id]);

        $this->actingAs($admin)
            ->get("/work-orders/{$workOrderA->id}/edit")
            ->assertForbidden();

        $admin->forceFill(['active_company_id' => $companyA->id])->save();

        $this->actingAs($admin)
            ->getJson('/work-orders/data')
            ->assertOk()
            ->assertJsonFragment(['id' => $workOrderA->id])
            ->assertJsonMissing(['id' => $workOrderB->id]);
    }

    public function test_create_work_order_from_contract_prefills_and_persists_relation(): void
    {
        [$admin, $establishment, , , $received, , $company] = $this->seedContext();

        $clientId = (int) $establishment->company_id;
        $status = ContractStatus::query()->create([
            'name' => 'Abierto',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $contract = Contract::query()->create([
            'code' => 'C-OT-1',
            'company_id' => $clientId,
            'responsible_user_id' => $admin->id,
            'contract_status_id' => $status->id,
            'description' => 'Linked contract',
            'work_order_subject' => 'Visit from contract',
        ]);
        $contract->establishments()->sync([$establishment->id]);

        $this->actingAs($admin)
            ->get('/work-orders/create?contract_id='.$contract->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkOrders/Create')
                ->where('defaultContractId', $contract->id)
                ->where('defaultEstablishmentId', $establishment->id)
                ->where('defaultSubject', 'Visit from contract'));

        $this->actingAs($admin)
            ->post('/work-orders', [
                'subject' => 'Visit from contract',
                'status_id' => $received->id,
                'establishment_id' => $establishment->id,
                'contract_id' => $contract->id,
                'is_urgent' => false,
                'code' => 'OT-CONTRACT-1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'work_order_created_successfully');

        $workOrder = WorkOrder::query()->where('subject', 'Visit from contract')->firstOrFail();

        $this->assertSame($contract->id, (int) $workOrder->contract_id);

        $this->actingAs($admin)
            ->get("/work-orders/{$workOrder->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkOrders/Edit')
                ->where('workOrder.contract_id', $contract->id));

        $this->actingAs($admin)
            ->getJson('/work-orders/data?contract_id='.$contract->id.'&pending=')
            ->assertOk()
            ->assertJsonPath('data.0.id', $workOrder->id);

        unset($company);
    }

    /**
     * @return array{0: User, 1: Establishment, 2: WorkOrderStatus, 3: WorkOrderStatus, 4: WorkOrderStatus, 5: WorkOrderStatus, 6: Company}
     */
    private function seedContext(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($admin, $company);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $pending = $this->makeStatus(
            101,
            WorkOrderStage::Estimate,
            'Pendiente',
            1,
            true,
            ['is_default' => true],
        );
        $approved = $this->makeStatus(
            202,
            WorkOrderStage::Estimate,
            'Aprobado',
            6,
            false,
            ['confirms_estimate' => true],
        );
        $received = $this->makeStatus(
            303,
            WorkOrderStage::WorkOrder,
            'Recibida - OK por Organizar',
            2,
            true,
            ['is_post_confirm_default' => true],
        );
        $rejected = $this->makeStatus(
            404,
            WorkOrderStage::WorkOrder,
            'Rechazada - Presupuesto',
            2,
            false,
            ['rejects_to_estimate' => true],
        );

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'name' => 'Store 1',
            'code' => 'S1',
        ]);

        return [$admin, $establishment, $pending, $approved, $received, $rejected, $company];
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function makeStatus(
        int $id,
        WorkOrderStage $kind,
        string $name,
        int $lifecycle,
        bool $isOpen,
        array $flags = [],
    ): WorkOrderStatus {
        $status = new WorkOrderStatus;
        $status->forceFill([
            'id' => $id,
            'name' => $name,
            'kind' => $kind,
            'lifecycle' => $lifecycle,
            'is_open' => $isOpen,
            'is_default' => $flags['is_default'] ?? false,
            'confirms_estimate' => $flags['confirms_estimate'] ?? false,
            'rejects_to_estimate' => $flags['rejects_to_estimate'] ?? false,
            'is_post_confirm_default' => $flags['is_post_confirm_default'] ?? false,
            'sets_sent_at' => $flags['sets_sent_at'] ?? false,
        ])->save();

        return $status;
    }
}
