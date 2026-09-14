<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Domain\Companies\Enums\TechnicianRatingSource;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\TechnicianRate;
use App\Models\TechnicianRating;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class TechnicianRatesRatingsAndEstablishmentListsTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_upsert_technician_rates(): void
    {
        [$admin, $relationship] = $this->technicianContext();

        $this->actingAs($admin)
            ->getJson("/technicians/{$relationship->id}/rates")
            ->assertOk()
            ->assertJsonPath('data.company_relationship_id', $relationship->id)
            ->assertJsonPath('data.labor_weekday_amount', '0.00');

        $this->actingAs($admin)
            ->putJson("/technicians/{$relationship->id}/rates", [
                'labor_weekday_amount' => 45.5,
                'labor_night_amount' => 60,
                'travel_urgent_amount' => 25.25,
            ])
            ->assertOk()
            ->assertJsonPath('data.labor_weekday_amount', '45.50')
            ->assertJsonPath('data.labor_night_amount', '60.00')
            ->assertJsonPath('data.travel_urgent_amount', '25.25');

        $this->assertDatabaseHas('technician_rates', [
            'company_relationship_id' => $relationship->id,
            'labor_weekday_amount' => 45.5,
            'labor_night_amount' => 60,
            'travel_urgent_amount' => 25.25,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->putJson("/technicians/{$relationship->id}/rates", [
                'labor_weekday_amount' => 50,
            ])
            ->assertOk()
            ->assertJsonPath('data.labor_weekday_amount', '50.00');

        $this->assertSame(
            1,
            TechnicianRate::query()->where('company_relationship_id', $relationship->id)->count(),
        );
    }

    public function test_technician_rating_updates_relationship_aggregates(): void
    {
        [$admin, $relationship] = $this->technicianContext();

        $this->actingAs($admin)
            ->postJson("/technicians/{$relationship->id}/ratings", [
                'score' => 8,
                'source' => TechnicianRatingSource::Optima->value,
                'notes' => 'Good work',
            ])
            ->assertCreated()
            ->assertJsonPath('data.score', 8)
            ->assertJsonPath('data.aggregates.optima_score', '8.00')
            ->assertJsonPath('data.aggregates.optima_score_count', 1)
            ->assertJsonPath('data.aggregates.average_score', '8.00');

        $this->actingAs($admin)
            ->postJson("/technicians/{$relationship->id}/ratings", [
                'score' => 6,
                'source' => TechnicianRatingSource::Customer->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.aggregates.customer_score', '6.00')
            ->assertJsonPath('data.aggregates.customer_score_count', 1)
            ->assertJsonPath('data.aggregates.average_score', '7.00');

        $relationship->refresh();
        $this->assertSame('8.00', (string) $relationship->optima_score);
        $this->assertSame('6.00', (string) $relationship->customer_score);
        $this->assertSame('7.00', (string) $relationship->average_score);
        $this->assertSame(1, $relationship->optima_score_count);
        $this->assertSame(1, $relationship->customer_score_count);

        $this->actingAs($admin)
            ->postJson("/technicians/{$relationship->id}/ratings", [
                'score' => 10,
                'source' => TechnicianRatingSource::Optima->value,
                'notes' => 'Updated optima',
            ])
            ->assertCreated()
            ->assertJsonPath('data.aggregates.optima_score', '10.00')
            ->assertJsonPath('data.aggregates.optima_score_count', 1);

        $this->assertSame(
            1,
            TechnicianRating::query()
                ->where('company_relationship_id', $relationship->id)
                ->where('source', TechnicianRatingSource::Optima->value)
                ->whereNull('work_order_id')
                ->count(),
        );

        $this->actingAs($admin)
            ->getJson("/technicians/{$relationship->id}/ratings")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_establishment_syncs_blacklist_and_favorites(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $techACompany = Company::factory()->create(['name' => 'Tech A']);
        $techBCompany = Company::factory()->create(['name' => 'Tech B']);
        $admin = $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        $techA = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $techACompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'status' => CompanyRelationshipStatus::Active,
        ]);
        $techB = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $techBCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/establishments/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Establishments/Create')
                ->has('technicianOptions', 2));

        $this->actingAs($admin)
            ->post('/establishments', [
                'company_id' => $client->id,
                'name' => 'Site with lists',
                'code' => 'EST-TECH-1',
                'collaborator_ids' => [],
                'blocked_technician_ids' => [$techA->id],
                'favorite_technician_ids' => [$techB->id],
            ])
            ->assertRedirect();

        $establishment = Establishment::query()->where('code', 'EST-TECH-1')->firstOrFail();

        $this->assertDatabaseHas('establishment_technician_blacklist', [
            'establishment_id' => $establishment->id,
            'company_relationship_id' => $techA->id,
        ]);
        $this->assertDatabaseHas('establishment_favorite_technicians', [
            'establishment_id' => $establishment->id,
            'company_relationship_id' => $techB->id,
        ]);

        $this->actingAs($admin)
            ->get("/establishments/{$establishment->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('establishment.blocked_technician_ids', [$techA->id])
                ->where('establishment.favorite_technician_ids', [$techB->id]));

        $this->actingAs($admin)
            ->put("/establishments/{$establishment->id}", [
                'company_id' => $client->id,
                'name' => 'Site with lists',
                'code' => 'EST-TECH-1',
                'collaborator_ids' => [],
                'blocked_technician_ids' => [$techB->id],
                'favorite_technician_ids' => [$techA->id, $techB->id],
            ])
            ->assertRedirect(route('establishments.edit', $establishment));

        $this->assertDatabaseMissing('establishment_technician_blacklist', [
            'establishment_id' => $establishment->id,
            'company_relationship_id' => $techA->id,
        ]);
        $this->assertDatabaseHas('establishment_technician_blacklist', [
            'establishment_id' => $establishment->id,
            'company_relationship_id' => $techB->id,
        ]);
        $this->assertDatabaseHas('establishment_favorite_technicians', [
            'establishment_id' => $establishment->id,
            'company_relationship_id' => $techA->id,
        ]);
        $this->assertDatabaseHas('establishment_favorite_technicians', [
            'establishment_id' => $establishment->id,
            'company_relationship_id' => $techB->id,
        ]);
    }

    /**
     * @return array{0: User, 1: CompanyRelationship}
     */
    private function technicianContext(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $technicianCompany = Company::factory()->create(['name' => 'Field Tech']);
        $admin = $this->attachToCompany($admin, $owner);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'status' => CompanyRelationshipStatus::Active,
        ]);

        return [$admin, $relationship];
    }
}
