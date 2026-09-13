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

final class EstimatesCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_update_and_delete_estimates(): void
    {
        [$admin, $establishment, $pending] = $this->seedContext();

        $this->actingAs($admin)
            ->get('/estimates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'Replace filter',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-100',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'estimate_created_successfully');

        $estimate = WorkOrder::query()->where('subject', 'Replace filter')->firstOrFail();

        $this->assertTrue($estimate->isEstimate());
        $this->assertSame($pending->id, $estimate->status_id);
        $this->assertNull($estimate->confirmed_at);

        $this->actingAs($admin)
            ->getJson('/estimates/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $estimate->id)
            ->assertJsonPath('data.0.stage', 'estimate');

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->where('estimate.subject', 'Replace filter'));

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Replace filter updated',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => true,
                'code' => 'EST-100',
            ])
            ->assertRedirect(route('estimates.edit', $estimate))
            ->assertSessionHas('success', 'estimate_updated_successfully');

        $this->assertDatabaseHas('work_orders', [
            'id' => $estimate->id,
            'subject' => 'Replace filter updated',
            'is_urgent' => true,
            'stage' => 'estimate',
        ]);

        $this->actingAs($admin)
            ->delete("/estimates/{$estimate->id}")
            ->assertRedirect(route('estimates.index'))
            ->assertSessionHas('success', 'estimate_deleted_successfully');

        $this->assertSoftDeleted($estimate);
    }

    public function test_approving_an_estimate_confirms_it_and_redirects_to_work_orders(): void
    {
        [$admin, $establishment, $pending, $approved, $received] = $this->seedContext();

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Quote to confirm',
            'code' => 'PR26/00001',
        ]);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Quote to confirm',
                'status_id' => $approved->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => $estimate->code,
            ])
            ->assertRedirect(route('work-orders.edit', $estimate))
            ->assertSessionHas('success', 'work_order_confirmed_successfully');

        $estimate->refresh();

        $this->assertTrue($estimate->isConfirmedWorkOrder());
        $this->assertSame($received->id, $estimate->status_id);
        $this->assertNotNull($estimate->confirmed_at);
        $this->assertStringContainsString('viene de PR26/00001', (string) $estimate->subject);
    }

    public function test_estimate_routes_reject_confirmed_work_orders(): void
    {
        [$admin, $establishment, , , $received] = $this->seedContext();

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $received->id,
            'subject' => 'Already OT',
        ]);

        $this->actingAs($admin)
            ->get("/estimates/{$workOrder->id}/edit")
            ->assertNotFound();
    }

    public function test_user_without_permission_cannot_view_estimates(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $company = Company::factory()->create();
        $this->attachToCompany($viewer, $company);

        $this->actingAs($viewer)
            ->get('/estimates')
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
