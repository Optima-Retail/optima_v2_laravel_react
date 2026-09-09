<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Establishment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class ContractsCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_contracts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($admin, $company);

        $brand = Brand::query()->create(['name' => 'Acme Brand']);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
            'brand_id' => $brand->id,
        ]);

        $status = ContractStatus::query()->create([
            'name' => 'Abierto',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $inactiveStatus = ContractStatus::query()->create([
            'name' => 'Cerrado',
            'color' => '#e2e8f0',
            'lifecycle' => 9,
            'is_open' => false,
        ]);

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'name' => 'Store 1',
            'code' => 'S1',
        ]);

        $this->actingAs($admin)
            ->get('/contracts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('contracts'));

        $this->actingAs($admin)
            ->get('/contracts/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Create')
                ->where('contractStatusOptions', fn ($options) => collect($options)->every(
                    fn ($option) => (int) $option['id'] !== (int) $inactiveStatus->id,
                )));

        $this->actingAs($admin)
            ->post('/contracts', [
                'description' => 'Maintenance contract',
                'company_id' => $client->id,
                'responsible_user_id' => $admin->id,
                'contract_status_id' => $status->id,
                'work_order_subject' => 'Preventive visit',
                'signed_at' => '2026-01-15',
                'establishment_ids' => [$establishment->id],
            ])
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('success', 'contract_created_successfully');

        $contract = Contract::query()->where('description', 'Maintenance contract')->firstOrFail();
        $this->assertSame('C00001', $contract->code);
        $this->assertSame($client->id, $contract->company_id);
        $this->assertTrue($contract->establishments()->where('establishments.id', $establishment->id)->exists());

        $this->actingAs($admin)
            ->getJson('/contracts/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $contract->id)
            ->assertJsonPath('data.0.description', 'Maintenance contract')
            ->assertJsonPath('data.0.brand_name', 'Acme Brand')
            ->assertJsonMissingPath('data.0.progress')
            ->assertJsonMissingPath('data.0.responsible_user_name');

        $this->actingAs($admin)
            ->put("/contracts/{$contract->id}", [
                'code' => 'C00001',
                'description' => 'Maintenance contract updated',
                'company_id' => $client->id,
                'responsible_user_id' => $admin->id,
                'contract_status_id' => $status->id,
                'work_order_subject' => 'Preventive visit',
                'signed_at' => '2026-01-15',
                'establishment_ids' => [$establishment->id],
            ])
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('success', 'contract_updated_successfully');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'description' => 'Maintenance contract updated',
        ]);

        $this->actingAs($admin)
            ->delete("/contracts/{$contract->id}")
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('success', 'contract_deleted_successfully');

        $this->assertSoftDeleted($contract);
    }

    public function test_user_without_permission_cannot_view_contracts(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $company = Company::factory()->create();
        $this->attachToCompany($viewer, $company);

        $this->actingAs($viewer)
            ->get('/contracts')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/contracts/data')
            ->assertForbidden();
    }
}
