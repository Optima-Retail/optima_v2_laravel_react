<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Contracts\Enums\ContractIterationPeriodicity;
use App\Domain\Contracts\Enums\ContractIterationPeriodicityKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\ContractInvoicingAggregation;
use App\Models\ContractIteration;
use App\Models\ContractStatus;
use App\Models\Establishment;
use App\Models\User;
use App\Models\WorkOrderType;
use Database\Seeders\ContractStatusSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class ContractIterationCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ContractStatusSeeder::class);
    }

    public function test_admin_can_create_contract_with_iteration_and_aggregation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $establishment = Establishment::factory()->create(['company_id' => $client->id]);
        $workOrderType = WorkOrderType::query()->create([
            'name' => 'Maintenance',
            'code' => 'MNT',
            'color' => '#aabbcc',
        ]);
        $statusId = (int) ContractStatus::query()->where('is_open', true)->value('id');

        $response = $this->actingAs($admin)->post(route('contracts.store'), [
            'description' => 'Annual maintenance',
            'company_id' => $client->id,
            'responsible_user_id' => $admin->id,
            'contract_status_id' => $statusId,
            'establishment_ids' => [$establishment->id],
            'invoicing_aggregations' => [
                [
                    'temp_key' => 'agg_1',
                    'subject' => 'Monthly billing',
                    'billing_frequency' => 'monthly',
                    'billing_day' => 1,
                    'per_establishment' => false,
                ],
            ],
            'iterations' => [
                [
                    'temp_key' => 'it_1',
                    'subject' => 'Weekly visit',
                    'work_order_type_id' => $workOrderType->id,
                    'starts_on' => '2026-01-01',
                    'ends_on' => '2026-12-31',
                    'periodicity' => ContractIterationPeriodicity::Weekly->value,
                    'periodicity_kind' => ContractIterationPeriodicityKind::Basic->value,
                    'interval' => 7,
                    'weekdays' => [1, 3],
                    'month_days' => [],
                    'months' => [],
                    'cost_amount' => 120.5,
                    'establishment_ids' => [$establishment->id],
                    'invoicing_aggregation_temp_key' => 'agg_1',
                ],
            ],
        ]);

        $contract = Contract::query()->first();
        $this->assertNotNull($contract);

        $response
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('success', 'contract_created_successfully');

        $aggregation = ContractInvoicingAggregation::query()->where('contract_id', $contract->id)->first();
        $this->assertNotNull($aggregation);
        $this->assertSame('Monthly billing', $aggregation->subject);

        $iteration = ContractIteration::query()->where('contract_id', $contract->id)->first();
        $this->assertNotNull($iteration);
        $this->assertSame('Weekly visit', $iteration->subject);
        $this->assertSame($workOrderType->id, $iteration->work_order_type_id);
        $this->assertSame($aggregation->id, $iteration->invoicing_aggregation_id);
        $this->assertSame([1, 3], $iteration->weekdays);
        $this->assertSame([$establishment->id], $iteration->establishment_ids);
    }

    public function test_admin_can_update_contract_iterations(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $workOrderType = WorkOrderType::query()->create([
            'name' => 'Audit',
            'code' => 'AUD',
            'color' => '#ccddee',
        ]);
        $statusId = (int) ContractStatus::query()->where('is_open', true)->value('id');

        $contract = Contract::query()->create([
            'description' => 'Base contract',
            'company_id' => $client->id,
            'responsible_user_id' => $admin->id,
            'contract_status_id' => $statusId,
        ]);

        $iteration = ContractIteration::query()->create([
            'contract_id' => $contract->id,
            'work_order_type_id' => $workOrderType->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-06-30',
            'periodicity' => ContractIterationPeriodicity::Monthly,
            'periodicity_kind' => ContractIterationPeriodicityKind::Complex,
            'interval' => null,
            'weekdays' => [],
            'month_days' => [1, 15],
            'months' => [1, 2, 3],
            'cost_amount' => 50,
            'subject' => 'Old subject',
            'establishment_ids' => [],
        ]);

        $response = $this->actingAs($admin)->put(route('contracts.update', $contract), [
            'description' => 'Base contract',
            'company_id' => $client->id,
            'responsible_user_id' => $admin->id,
            'contract_status_id' => $statusId,
            'establishment_ids' => [],
            'invoicing_aggregations' => [],
            'iterations' => [
                [
                    'id' => $iteration->id,
                    'subject' => 'Updated subject',
                    'work_order_type_id' => $workOrderType->id,
                    'starts_on' => '2026-01-01',
                    'ends_on' => '2026-12-31',
                    'periodicity' => ContractIterationPeriodicity::Monthly->value,
                    'periodicity_kind' => ContractIterationPeriodicityKind::Complex->value,
                    'month_days' => [1],
                    'months' => [1, 6, 12],
                    'cost_amount' => 75,
                    'establishment_ids' => [],
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('success', 'contract_updated_successfully');

        $iteration->refresh();
        $this->assertSame('Updated subject', $iteration->subject);
        $this->assertSame([1], $iteration->month_days);
        $this->assertSame('75.00', (string) $iteration->cost_amount);
        $this->assertSame(1, ContractIteration::query()->where('contract_id', $contract->id)->count());
    }
}
