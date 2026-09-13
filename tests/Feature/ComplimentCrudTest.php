<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Compliments\Enums\ComplimentSubjectType;
use App\Domain\QualityScores\Enums\QualityActionId;
use App\Domain\QualityScores\Enums\QualityScoreDocumentType;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Compliment;
use App\Models\ComplimentType;
use App\Models\Establishment;
use App\Models\User;
use App\Models\UserActionScore;
use Database\Seeders\ActionSeeder;
use Database\Seeders\ComplimentTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class ComplimentCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(ComplimentTypeSeeder::class);
    }

    public function test_admin_can_create_compliment_for_establishment_and_scores_users(): void
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

        $establishment = Establishment::factory()->create([
            'company_id' => $client->id,
        ]);

        $recipient = User::factory()->create(['quality_score' => 0]);
        $this->attachToCompany($recipient, $owner, active: false);

        $type = ComplimentType::query()->findOrFail(1);

        $response = $this->actingAs($admin)
            ->post(route('compliments.store'), [
                'subject_type' => ComplimentSubjectType::Establishment->value,
                'establishment_id' => $establishment->id,
                'compliment_type_id' => $type->id,
                'comment' => 'Great service',
                'user_ids' => [$recipient->id],
                'score' => 5,
            ]);

        $compliment = Compliment::query()->first();
        $this->assertNotNull($compliment);

        $response
            ->assertRedirect(route('compliments.edit', $compliment))
            ->assertSessionHas('success', 'compliment_created_successfully');

        $this->assertSame(ComplimentSubjectType::Establishment, $compliment->subject_type);
        $this->assertSame($establishment->id, $compliment->establishment_id);
        $this->assertDatabaseHas('compliment_user', [
            'compliment_id' => $compliment->id,
            'user_id' => $recipient->id,
            'score' => 5,
        ]);

        $this->assertDatabaseHas('user_action_scores', [
            'user_id' => $recipient->id,
            'action_id' => QualityActionId::Felicitacion->value,
            'document_type' => QualityScoreDocumentType::Compliment->value,
            'document_id' => $compliment->id,
        ]);

        $this->assertInstanceOf(UserActionScore::class, UserActionScore::query()->first());
    }
}
