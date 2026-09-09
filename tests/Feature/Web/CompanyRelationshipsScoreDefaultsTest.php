<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class CompanyRelationshipsScoreDefaultsTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creating_technician_defaults_null_score_counts_to_zero(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $technicianCompany = Company::factory()->create();
        $this->attachToCompany($admin, $company);

        $this->actingAs($admin)
            ->post('/suppliers', [
                'related_mode' => 'existing',
                'related_company_id' => $technicianCompany->id,
                'kind' => CompanyRelationshipKind::Technician->value,
                'status' => 'active',
                'classification' => 'commercial',
                'optima_score' => null,
                'customer_score' => null,
                'average_score' => null,
                'optima_score_count' => null,
                'customer_score_count' => null,
            ])
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success', 'supplier_created_successfully');

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $company->id)
            ->where('related_company_id', $technicianCompany->id)
            ->where('kind', CompanyRelationshipKind::Technician)
            ->firstOrFail();

        $this->assertSame(0, $relationship->optima_score_count);
        $this->assertSame(0, $relationship->customer_score_count);
    }
}
