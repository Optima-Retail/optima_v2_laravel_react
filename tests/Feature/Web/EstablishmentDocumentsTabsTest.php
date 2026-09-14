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

final class EstablishmentDocumentsTabsTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_establishment_edit_exposes_work_order_and_estimate_totals(): void
    {
        [$admin, $establishment, $pending, $received] = $this->seedContext();

        WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $received->id,
            'subject' => 'Site OT',
            'total_euros' => 100,
            'cost_amount' => 40,
        ]);

        WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Site estimate',
            'total_euros' => 50,
            'cost_amount' => 10,
        ]);

        $this->actingAs($admin)
            ->get("/establishments/{$establishment->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Establishments/Edit')
                ->where('can.view_work_orders', true)
                ->where('can.create_work_orders', true)
                ->where('can.view_estimates', true)
                ->where('can.create_estimates', true)
                ->where('workOrderTotals.count', 1)
                ->where('workOrderTotals.total_amount', 100)
                ->where('workOrderTotals.cost_amount', 40)
                ->where('workOrderTotals.margin_percentage', 60)
                ->where('estimateTotals.count', 1)
                ->where('estimateTotals.total_amount', 50)
                ->where('estimateTotals.cost_amount', 10)
                ->where('estimateTotals.margin_percentage', 80));
    }

    public function test_work_order_and_estimate_data_can_be_filtered_by_establishment(): void
    {
        [$admin, $establishment, $pending, $received] = $this->seedContext();

        $otherEstablishment = Establishment::factory()->create([
            'company_id' => $establishment->company_id,
            'name' => 'Other site',
        ]);

        $own = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $received->id,
            'subject' => 'Own OT',
        ]);

        WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $otherEstablishment->id,
            'status_id' => $received->id,
            'subject' => 'Other OT',
        ]);

        $ownEstimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Own estimate',
        ]);

        WorkOrder::factory()->create([
            'establishment_id' => $otherEstablishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Other estimate',
        ]);

        $this->actingAs($admin)
            ->getJson('/work-orders/data?establishment_id='.$establishment->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->actingAs($admin)
            ->getJson('/estimates/data?establishment_id='.$establishment->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownEstimate->id);
    }

    public function test_create_work_order_and_estimate_prefills_establishment_from_query(): void
    {
        [$admin, $establishment] = $this->seedContext();

        $this->actingAs($admin)
            ->get('/work-orders/create?establishment_id='.$establishment->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkOrders/Create')
                ->where('defaultEstablishmentId', $establishment->id)
                ->where('defaultContractId', null));

        $this->actingAs($admin)
            ->get('/estimates/create?establishment_id='.$establishment->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Create')
                ->where('defaultEstablishmentId', $establishment->id)
                ->where('defaultContractId', null));
    }

    /**
     * @return array{0: User, 1: Establishment, 2: WorkOrderStatus, 3: WorkOrderStatus}
     */
    private function seedContext(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create(['name' => 'Owner Co']);
        $client = Company::factory()->create(['name' => 'Client Co']);
        $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $establishment = Establishment::factory()->create([
            'company_id' => $client->id,
            'name' => 'Main site',
        ]);

        $pending = WorkOrderStatus::query()->firstOrCreate(
            ['name' => 'Pendiente', 'kind' => WorkOrderStage::Estimate],
            ['color' => '#f6eac2', 'lifecycle' => 1, 'is_open' => true],
        );

        $received = WorkOrderStatus::query()->firstOrCreate(
            ['name' => 'Recibida - OK por Organizar', 'kind' => WorkOrderStage::WorkOrder],
            ['color' => '#f6eac2', 'lifecycle' => 2, 'is_open' => true],
        );

        return [$admin, $establishment, $pending, $received];
    }
}
