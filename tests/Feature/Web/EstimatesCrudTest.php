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
use App\Models\WorkOrderStatusTransition;
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
                ->where('estimate.subject', 'Replace filter')
                ->where('estimate.status_is_open', true)
                ->where('fields_locked', false)
                ->where('can.update_closed', true));

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
        $this->assertNotSame(7, $approved->id);
        $this->assertNotSame(14, $received->id);
    }

    public function test_entering_sent_and_closed_statuses_stamps_dates_without_legacy_ids(): void
    {
        [$admin, $establishment, $pending] = $this->seedContext();
        $sent = $this->makeStatus(606, WorkOrderStage::Estimate, 'Sent to client', 5, true, ['sets_sent_at' => true]);
        $closed = $this->makeStatus(505, WorkOrderStage::Estimate, 'Closed estimate', 9, false);

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Stamp dates',
            'code' => 'EST-SENT',
        ]);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Stamp dates',
                'status_id' => $sent->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-SENT',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertNotNull($estimate->sent_at);
        $this->assertNull($estimate->closed_at);
        $this->assertNotSame(5, $sent->id);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Stamp dates',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-SENT',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertNotNull($estimate->closed_at);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Stamp dates reopened',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-SENT',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertNull($estimate->closed_at);
        $this->assertSame('Stamp dates reopened', $estimate->subject);
    }

    public function test_closed_estimate_rejects_field_changes_without_update_closed(): void
    {
        [$admin, $establishment, $pending, , , , $company] = $this->seedContext();
        $closed = $this->makeStatus(505, WorkOrderStage::Estimate, 'Closed estimate', 9, false);

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $closed->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Locked quote',
            'code' => 'EST-LOCK',
        ]);

        $editor = User::factory()->create();
        $editor->givePermissionTo(['estimates.view', 'estimates.update', 'estimates.create']);
        $this->attachToCompany($editor, $company);

        $this->actingAs($editor)
            ->from("/estimates/{$estimate->id}/edit")
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Hacked subject',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-LOCK',
            ])
            ->assertRedirect("/estimates/{$estimate->id}/edit")
            ->assertSessionHasErrors('subject');

        $estimate->refresh();
        $this->assertSame('Locked quote', $estimate->subject);

        $this->actingAs($editor)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Locked quote',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-LOCK',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertSame($pending->id, $estimate->status_id);
        $this->assertSame('Locked quote', $estimate->subject);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Admin can edit closed',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-LOCK',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertSame('Admin can edit closed', $estimate->subject);
    }

    public function test_forbidden_status_transition_is_rejected_and_justification_is_required(): void
    {
        [$admin, $establishment, $pending] = $this->seedContext();
        $sent = $this->makeStatus(606, WorkOrderStage::Estimate, 'Sent to client', 5, true, ['sets_sent_at' => true]);
        $other = $this->makeStatus(707, WorkOrderStage::Estimate, 'Other estimate', 8, true);

        WorkOrderStatusTransition::query()->create([
            'from_status_id' => $pending->id,
            'to_status_id' => $sent->id,
            'requires_confirmation' => false,
            'requires_justification' => true,
        ]);

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Transition check',
            'code' => 'EST-TR',
        ]);

        $this->actingAs($admin)
            ->from("/estimates/{$estimate->id}/edit")
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Transition check',
                'status_id' => $other->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-TR',
            ])
            ->assertRedirect("/estimates/{$estimate->id}/edit")
            ->assertSessionHasErrors('status_id');

        $this->actingAs($admin)
            ->from("/estimates/{$estimate->id}/edit")
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Transition check',
                'status_id' => $sent->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-TR',
            ])
            ->assertRedirect("/estimates/{$estimate->id}/edit")
            ->assertSessionHasErrors('status_justification');

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Transition check',
                'status_id' => $sent->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-TR',
                'status_justification' => 'Client asked to send this quote.',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertSame($sent->id, $estimate->status_id);
        $this->assertNotNull($estimate->sent_at);
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
