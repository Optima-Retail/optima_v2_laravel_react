<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
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
                ->where('workOrder.subject', 'Install unit'));

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

        $clone = WorkOrder::query()
            ->where('source_work_order_id', $workOrder->id)
            ->firstOrFail();

        $this->assertTrue($clone->isEstimate());
        $this->assertSame($pending->id, $clone->status_id);
        $this->assertStringContainsString('viene de WO-200', (string) $clone->subject);
        $this->assertNotSame($workOrder->id, $clone->id);

        $response->assertRedirect(route('estimates.edit', $clone));
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

    /**
     * @return array{0: User, 1: Establishment, 2: WorkOrderStatus, 3: WorkOrderStatus, 4: WorkOrderStatus, 5: WorkOrderStatus}
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
            WorkOrder::DEFAULT_ESTIMATE_STATUS_ID,
            WorkOrderStage::Estimate,
            'Pendiente',
            1,
            true,
        );
        $approved = $this->makeStatus(
            WorkOrder::APPROVED_ESTIMATE_STATUS_ID,
            WorkOrderStage::Estimate,
            'Aprobado',
            6,
            false,
        );
        $received = $this->makeStatus(
            WorkOrder::DEFAULT_CONFIRMED_STATUS_ID,
            WorkOrderStage::WorkOrder,
            'Recibida - OK por Organizar',
            2,
            true,
        );
        $rejected = $this->makeStatus(
            WorkOrder::REJECTED_TO_ESTIMATE_STATUS_ID,
            WorkOrderStage::WorkOrder,
            'Rechazada - Presupuesto',
            2,
            false,
        );

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'name' => 'Store 1',
            'code' => 'S1',
        ]);

        return [$admin, $establishment, $pending, $approved, $received, $rejected];
    }

    private function makeStatus(
        int $id,
        WorkOrderStage $kind,
        string $name,
        int $lifecycle,
        bool $isOpen,
    ): WorkOrderStatus {
        $status = new WorkOrderStatus;
        $status->forceFill([
            'id' => $id,
            'name' => $name,
            'kind' => $kind,
            'lifecycle' => $lifecycle,
            'is_open' => $isOpen,
        ])->save();

        return $status;
    }
}
