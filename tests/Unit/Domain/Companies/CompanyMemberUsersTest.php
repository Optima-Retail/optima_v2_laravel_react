<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Companies;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class CompanyMemberUsersTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_options_only_include_active_company_members(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $memberA = User::factory()->create(['name' => 'Alice']);
        $memberB = User::factory()->create(['name' => 'Bob']);
        $outsider = User::factory()->create(['name' => 'Carol']);

        $this->attachToCompany($memberA, $companyA, active: false);
        $this->attachToCompany($memberB, $companyB, active: false);
        // outsider has no membership

        $options = CompanyMemberUsers::options($companyA);

        $ids = array_column($options, 'id');

        $this->assertContains($memberA->id, $ids);
        $this->assertNotContains($memberB->id, $ids);
        $this->assertNotContains($outsider->id, $ids);
    }

    public function test_exists_rule_rejects_users_outside_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $this->attachToCompany($admin, $companyA);

        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->attachToCompany($memberA, $companyA, active: false);
        $this->attachToCompany($memberB, $companyB, active: false);

        $validatorA = validator(
            ['user_id' => $memberA->id],
            ['user_id' => ['required', CompanyMemberUsers::existsRule($companyA->id)]],
        );
        $validatorB = validator(
            ['user_id' => $memberB->id],
            ['user_id' => ['required', CompanyMemberUsers::existsRule($companyA->id)]],
        );

        $this->assertTrue($validatorA->passes());
        $this->assertTrue($validatorB->fails());
    }
}
