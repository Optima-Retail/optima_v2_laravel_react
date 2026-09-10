<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\Incident;
use App\Models\IncidentPriority;
use App\Models\IncidentStatus;
use App\Models\IncidentSubtype;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class IncidentsCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_incidents(): void
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

        $status = IncidentStatus::query()->create([
            'name' => 'Abierta - QC',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $priority = IncidentPriority::query()->create([
            'name' => 'Alto impacto',
            'color' => '#ff0000',
            'resolution_time_hours' => 4,
        ]);

        $type = IncidentType::query()->create([
            'id' => 11,
            'name' => 'QC',
            'color' => '#FFFFFF',
            'default_priority_id' => $priority->id,
            'origin_selectable' => false,
            'origin_options' => ['establishment'],
            'default_origin_type' => 'establishment',
            'origin_required' => true,
            'related_type' => 'evaluation',
            'show_related' => true,
        ]);

        $subtype = IncidentSubtype::query()->create([
            'name' => 'Nueva petición',
            'incident_type_id' => $type->id,
        ]);

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'name' => 'Store 1',
            'code' => 'S1',
        ]);

        $this->actingAs($admin)
            ->get('/incidents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Incidents/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete'));

        $this->actingAs($admin)
            ->get('/incidents/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Incidents/Create')
                ->has('typeWorkflow')
                ->where('defaultRequesterUserId', $admin->id));

        $this->actingAs($admin)
            ->post('/incidents', [
                'subject' => 'QC incident follow-up',
                'comment' => 'Initial comment',
                'incident_type_id' => $type->id,
                'incident_subtype_id' => $subtype->id,
                'incident_priority_id' => $priority->id,
                'incident_status_id' => $status->id,
                'responsible_user_id' => $admin->id,
                'collaborator_ids' => [$admin->id],
                'origin_type' => 'establishment',
                'origin_id' => $establishment->id,
                'related_type' => null,
                'related_id' => null,
            ])
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('success', 'incident_created_successfully');

        $incident = Incident::query()->where('subject', 'QC incident follow-up')->firstOrFail();

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'subject' => 'QC incident follow-up',
            'requester_user_id' => $admin->id,
            'origin_type' => 'establishment',
            'origin_id' => $establishment->id,
            'establishment_id' => $establishment->id,
        ]);
        $this->assertDatabaseHas('incident_collaborators', [
            'incident_id' => $incident->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/incidents/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $incident->id)
            ->assertJsonPath('data.0.subject', 'QC incident follow-up')
            ->assertJsonPath('data.0.establishment_name', 'Store 1')
            ->assertJsonPath('data.0.status_name', 'Abierta - QC');

        $this->actingAs($admin)
            ->get("/incidents/{$incident->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Incidents/Edit')
                ->where('incident.subject', 'QC incident follow-up')
                ->where('incident.origin_type', 'establishment')
                ->where('incident.origin_id', $establishment->id)
                ->where('incident.collaborator_ids', [$admin->id])
                ->has('lines')
                ->has('can.create_line'));

        $review = IncidentStatus::query()->create([
            'name' => 'En revisión',
            'color' => '#c7dbda',
            'lifecycle' => 2,
            'is_open' => true,
        ]);

        $closed = IncidentStatus::query()->create([
            'name' => 'Finalizada',
            'color' => '#c7c7c7',
            'lifecycle' => 4,
            'is_open' => false,
        ]);

        $this->actingAs($admin)
            ->post("/incidents/{$incident->id}/lines", [
                'comment' => 'Closed statuses are not allowed in Acciones',
                'incident_status_id' => $closed->id,
            ])
            ->assertSessionHasErrors('incident_status_id');

        $this->actingAs($admin)
            ->post("/incidents/{$incident->id}/lines", [
                'comment' => 'Moved to review after follow-up',
                'incident_status_id' => $review->id,
            ])
            ->assertRedirect(route('incidents.edit', $incident))
            ->assertSessionHas('success', 'incident_line_created_successfully');

        $this->assertDatabaseHas('incident_lines', [
            'incident_id' => $incident->id,
            'comment' => 'Moved to review after follow-up',
            'incident_status_id' => $review->id,
            'user_id' => $admin->id,
        ]);

        $incident->refresh();
        $this->assertSame($review->id, $incident->incident_status_id);
        $this->assertNull($incident->closed_at);

        $this->actingAs($admin)
            ->put("/incidents/{$incident->id}", [
                'subject' => 'QC incident follow-up updated',
                'comment' => 'Updated comment',
                'incident_type_id' => $type->id,
                'incident_subtype_id' => $subtype->id,
                'incident_priority_id' => $priority->id,
                'incident_status_id' => $status->id,
                'responsible_user_id' => $admin->id,
                'collaborator_ids' => [],
                'origin_type' => 'establishment',
                'origin_id' => $establishment->id,
                'control_at' => '2026-02-02T11:00',
            ])
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('success', 'incident_updated_successfully');

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'subject' => 'QC incident follow-up updated',
            'comment' => 'Updated comment',
            'origin_type' => 'establishment',
            'origin_id' => $establishment->id,
        ]);
        $this->assertDatabaseMissing('incident_collaborators', [
            'incident_id' => $incident->id,
            'user_id' => $admin->id,
        ]);

        $incident->refresh();
        // Header save must not change status/type (same as Optima show — status only via Acciones).
        $this->assertSame($review->id, $incident->incident_status_id);
        $this->assertSame($type->id, $incident->incident_type_id);

        $this->actingAs($admin)
            ->delete("/incidents/{$incident->id}")
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('success', 'incident_deleted_successfully');

        $this->assertSoftDeleted($incident);
    }

    public function test_user_without_permission_cannot_view_incidents(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $company = Company::factory()->create();
        $this->attachToCompany($viewer, $company);

        $this->actingAs($viewer)
            ->get('/incidents')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/incidents/data')
            ->assertForbidden();
    }
}
