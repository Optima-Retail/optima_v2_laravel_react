<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class EstablishmentsCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_establishments(): void
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

        $this->actingAs($admin)
            ->get('/establishments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Establishments/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('establishments'));

        $this->actingAs($admin)
            ->post('/establishments', [
                'company_id' => $client->id,
                'name' => 'Main Warehouse',
                'code' => 'EST-001',
            ])
            ->assertRedirect(route('establishments.index'))
            ->assertSessionHas('success', 'establishment_created_successfully');

        $establishment = Establishment::query()->where('code', 'EST-001')->firstOrFail();
        $this->assertSame($client->id, $establishment->company_id);

        $this->actingAs($admin)
            ->put("/establishments/{$establishment->id}", [
                'company_id' => $client->id,
                'name' => 'Main Warehouse Updated',
                'code' => 'EST-001',
            ])
            ->assertRedirect(route('establishments.index'))
            ->assertSessionHas('success', 'establishment_updated_successfully');

        $this->assertDatabaseHas('establishments', [
            'id' => $establishment->id,
            'name' => 'Main Warehouse Updated',
        ]);

        $this->actingAs($admin)
            ->delete("/establishments/{$establishment->id}")
            ->assertRedirect(route('establishments.index'))
            ->assertSessionHas('success', 'establishment_deleted_successfully');

        $this->assertSoftDeleted($establishment);
    }

    public function test_establishments_data_is_scoped_to_the_active_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $otherClient = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $ownEstablishment = Establishment::factory()->create([
            'company_id' => $client->id,
            'name' => 'Alpha Depot',
        ]);
        Establishment::factory()->create([
            'company_id' => $otherClient->id,
            'name' => 'Outside Depot',
        ]);

        $this->actingAs($admin)
            ->getJson('/establishments/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $ownEstablishment->id);

        $this->actingAs($admin)
            ->getJson('/establishments/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Alpha',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $ownEstablishment->id);
    }

    public function test_user_without_permission_cannot_view_establishments(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $company = Company::factory()->create();
        $this->attachToCompany($viewer, $company);

        $this->actingAs($viewer)
            ->get('/establishments')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/establishments/data')
            ->assertForbidden();
    }
}
