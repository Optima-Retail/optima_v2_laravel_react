<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderConfirmationService;
use App\Models\Article;
use App\Models\Checklist;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\Requester;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderTechnicianStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class WorkOrderSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_can_be_confirmed_as_work_order_in_place(): void
    {
        $estimateStatus = WorkOrderStatus::query()->create([
            'name' => 'Aprobado',
            'kind' => WorkOrderStage::Estimate,
            'color' => '#97c1a9',
            'lifecycle' => 6,
            'is_open' => false,
        ]);

        $received = WorkOrderStatus::query()->create([
            'name' => 'Recibida - OK por Organizar',
            'kind' => WorkOrderStage::WorkOrder,
            'color' => '#f6eac2',
            'lifecycle' => 2,
            'is_open' => true,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'status_id' => $estimateStatus->id,
        ]);

        $this->assertTrue($workOrder->isEstimate());
        $this->assertNull($workOrder->confirmed_at);

        $confirmed = app(WorkOrderConfirmationService::class)->confirm($workOrder, $received->id);

        $this->assertTrue($confirmed->isConfirmedWorkOrder());
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertSame($received->id, $confirmed->status_id);
        $this->assertSame($workOrder->id, $confirmed->id);

        $this->expectException(InvalidArgumentException::class);
        app(WorkOrderConfirmationService::class)->confirm($confirmed);
    }

    public function test_work_order_children_and_parent_catalogs_persist(): void
    {
        $owner = Company::factory()->operating()->create();
        $party = Company::factory()->create();
        $technicianParty = Company::factory()->create();
        $establishment = Establishment::factory()->create(['company_id' => $party->id]);
        $requester = Requester::factory()->create(['company_id' => $party->id]);
        $user = User::factory()->create();

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianParty->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $estimateStatus = WorkOrderStatus::query()->create([
            'name' => 'Pendiente',
            'kind' => WorkOrderStage::Estimate,
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $attendance = WorkOrderTechnicianStatus::query()->create([
            'name' => 'Pendiente',
        ]);

        $workOrder = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'requester_id' => $requester->id,
            'responsible_user_id' => $user->id,
            'billing_company_id' => $party->id,
            'status_id' => $estimateStatus->id,
            'stage' => WorkOrderStage::Estimate,
        ]);

        $article = Article::query()->create(['code' => 'MAT-1', 'is_deletable' => true]);

        WorkOrderLine::query()->create([
            'work_order_id' => $workOrder->id,
            'article_id' => $article->id,
            'description' => 'Labour',
            'quantity' => 2,
            'unit_price' => 10,
            'net_amount' => 20,
            'sort_order' => 1,
        ]);

        $workOrder->technicians()->create([
            'company_relationship_id' => $technician->id,
            'is_selected' => true,
            'status_id' => $attendance->id,
            'quote_net_amount' => 20,
        ]);

        $workOrder->collaborators()->attach($user->id);

        $workOrder->attachments()->create([
            'name' => 'quote.pdf',
            'path' => 'work-orders/quote.pdf',
        ]);

        $checklist = Checklist::query()->create([
            'label' => 'Confirm site access',
            'document_type' => 'estimate',
            'work_order_status_id' => $estimateStatus->id,
            'sort_order' => 0,
        ]);

        $workOrder->checklistCompletions()->create([
            'checklist_id' => $checklist->id,
            'user_id' => $user->id,
            'is_validated' => true,
        ]);

        $workOrder->refresh();

        $this->assertCount(1, $workOrder->lines);
        $this->assertCount(1, $workOrder->technicians);
        $this->assertCount(1, $workOrder->collaborators);
        $this->assertCount(1, $workOrder->attachments);
        $this->assertCount(1, $workOrder->checklistCompletions);
        $this->assertTrue($establishment->workOrders()->whereKey($workOrder->id)->exists());
        $this->assertTrue($party->requesters()->whereKey($requester->id)->exists());
        $this->assertSame(WorkOrderStage::Estimate, $workOrder->stage);
    }
}
