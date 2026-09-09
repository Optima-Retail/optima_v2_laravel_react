<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\NumberingPattern;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class NumberingPatternsCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_configure_contract_pattern_for_company_and_auto_code_is_allocated(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($admin, $company);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $status = ContractStatus::query()->create([
            'name' => 'Abierto',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $this->actingAs($admin)
            ->put('/config/numbering-patterns/resource/contracts', [
                'segments' => [
                    ['type' => 'letters', 'value' => 'C'],
                    ['type' => 'symbols', 'value' => '-'],
                    ['type' => 'year'],
                    ['type' => 'symbols', 'value' => '-'],
                    ['type' => 'sequence', 'digit_length' => 4],
                ],
                'reset_yearly' => true,
                'is_active' => true,
                'return' => '/contracts',
            ])
            ->assertRedirect('/contracts')
            ->assertSessionHas('success', 'numbering_pattern_saved_successfully');

        $pattern = NumberingPattern::query()
            ->where('company_id', $company->id)
            ->where('resource', NumberingResource::Contracts->value)
            ->firstOrFail();

        $this->assertCount(5, $pattern->segments);
        $this->assertTrue($pattern->is_active);
        $this->assertSame($company->id, $pattern->company_id);

        $this->assertDatabaseMissing('numbering_patterns', [
            'company_id' => $otherCompany->id,
            'resource' => NumberingResource::Contracts->value,
        ]);

        $year = (int) now()->format('Y');
        $expected = "C-{$year}-0001";

        $this->actingAs($admin)
            ->get('/contracts/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contracts/Create')
                ->where('codeIsAutomatic', true)
                ->where('suggestedCode', $expected));

        $this->actingAs($admin)
            ->post('/contracts', [
                'description' => 'Patterned contract',
                'company_id' => $client->id,
                'responsible_user_id' => $admin->id,
                'contract_status_id' => $status->id,
                'establishment_ids' => [],
            ])
            ->assertRedirect(route('contracts.index'));

        $contract = Contract::query()->where('description', 'Patterned contract')->firstOrFail();
        $this->assertSame($expected, $contract->code);

        $pattern->refresh();
        $this->assertSame(1, $pattern->last_sequence);
        $this->assertSame($year, $pattern->last_year);
    }

    public function test_default_pattern_is_first_letter_plus_five_digits_per_company(): void
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

        $status = ContractStatus::query()->create([
            'name' => 'Abierto',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $this->actingAs($admin)
            ->get('/contracts/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contracts/Create')
                ->where('codeIsAutomatic', true)
                ->where('suggestedCode', 'C00001'));

        $this->actingAs($admin)
            ->post('/contracts', [
                'description' => 'Default patterned contract',
                'company_id' => $client->id,
                'responsible_user_id' => $admin->id,
                'contract_status_id' => $status->id,
                'establishment_ids' => [],
            ])
            ->assertRedirect(route('contracts.index'));

        $contract = Contract::query()->where('description', 'Default patterned contract')->firstOrFail();
        $this->assertSame('C00001', $contract->code);

        $pattern = NumberingPattern::query()
            ->where('company_id', $company->id)
            ->where('resource', NumberingResource::Contracts->value)
            ->firstOrFail();

        $this->assertSame([
            ['type' => 'letters', 'value' => 'C'],
            ['type' => 'sequence', 'digit_length' => 5],
        ], $pattern->segments);
        $this->assertFalse($pattern->reset_yearly);
        $this->assertSame(1, $pattern->last_sequence);
    }
}
