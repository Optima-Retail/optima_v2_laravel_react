<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\Evaluation;
use App\Models\EvaluationStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class EvaluationsCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_update_and_delete_evaluations(): void
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

        $status = EvaluationStatus::query()->create([
            'id' => 73,
            'name' => 'Abierta',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'name' => 'Store 1',
            'code' => 'S1',
        ]);

        $evaluation = Evaluation::query()->create([
            'subject' => 'QC visit follow-up',
            'public_id' => (string) Str::uuid(),
            'establishment_id' => $establishment->id,
            'evaluation_status_id' => $status->id,
            'responsible_user_id' => $admin->id,
            'next_action_at' => '2026-02-01 10:00:00',
            'facility_question' => 'Any facility issues?',
            'technician_question' => 'Technician notes?',
            'visit_count' => 0,
            'call_count' => 0,
        ]);

        $this->actingAs($admin)
            ->get('/evaluations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Evaluations/Index')
                ->has('filters')
                ->missing('can.create')
                ->has('can.update')
                ->has('can.delete'));

        $this->actingAs($admin)
            ->get('/evaluations/create')
            ->assertNotFound();

        $this->actingAs($admin)
            ->post('/evaluations', [
                'subject' => 'Should not create',
                'establishment_id' => $establishment->id,
                'evaluation_status_id' => $status->id,
            ])
            ->assertMethodNotAllowed();

        $this->actingAs($admin)
            ->getJson('/evaluations/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $evaluation->id)
            ->assertJsonPath('data.0.subject', 'QC visit follow-up')
            ->assertJsonPath('data.0.establishment_name', 'Store 1')
            ->assertJsonPath('data.0.status_name', 'Abierta');

        $this->actingAs($admin)
            ->get("/evaluations/{$evaluation->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Evaluations/Edit')
                ->where('evaluation.public_id', $evaluation->public_id)
                ->where('evaluation.visit_count', 0)
                ->where('evaluation.call_count', 0));

        $this->actingAs($admin)
            ->put("/evaluations/{$evaluation->id}", [
                'subject' => 'QC visit follow-up updated',
                'establishment_id' => $establishment->id,
                'evaluation_status_id' => $status->id,
                'responsible_user_id' => $admin->id,
                'next_action_at' => '2026-02-02T11:00',
                'facility_question' => 'Updated facility question',
                'technician_question' => 'Updated technician question',
            ])
            ->assertRedirect(route('evaluations.index'))
            ->assertSessionHas('success', 'evaluation_updated_successfully');

        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'subject' => 'QC visit follow-up updated',
        ]);

        $this->actingAs($admin)
            ->delete("/evaluations/{$evaluation->id}")
            ->assertRedirect(route('evaluations.index'))
            ->assertSessionHas('success', 'evaluation_deleted_successfully');

        $this->assertSoftDeleted($evaluation);
    }

    public function test_user_without_permission_cannot_view_evaluations(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $company = Company::factory()->create();
        $this->attachToCompany($viewer, $company);

        $this->actingAs($viewer)
            ->get('/evaluations')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/evaluations/data')
            ->assertForbidden();
    }
}
