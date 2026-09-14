<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\TechnicianIncident;
use App\Models\TechnicianIncidentMessage;
use App\Models\TechnicianIncidentStatus;
use App\Models\TechnicianIncidentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class TechnicianIncidentsCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_technician_incidents_and_open_technician_tab(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = TechnicianIncidentType::query()->create([
            'name' => 'Feedback',
            'due_days' => 1,
            'send_mail_to_technician' => false,
        ]);
        $status = TechnicianIncidentStatus::query()->create([
            'name' => 'Open',
            'is_open' => true,
            'lifecycle' => 1,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $incident = TechnicianIncident::query()->create([
            'status_id' => $status->id,
            'technician_incident_type_id' => $type->id,
            'incident_text' => 'Late attendance',
            'technician_id' => $technician->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);

        TechnicianIncidentMessage::query()->create([
            'technician_incident_id' => $incident->id,
            'user_id' => $admin->id,
            'body' => 'Please confirm',
            'type' => 'text',
        ]);

        $this->actingAs($admin)
            ->get('/technician-incidents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TechnicianIncidents/Index')
                ->has('filters'));

        $this->actingAs($admin)
            ->getJson('/technician-incidents/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $incident->id)
            ->assertJsonPath('data.0.technician_id', $technician->id);

        $this->actingAs($admin)
            ->getJson("/technician-incidents/{$incident->id}")
            ->assertOk()
            ->assertJsonPath('incident.id', $incident->id)
            ->assertJsonPath('messages.0.body', 'Please confirm');

        $this->actingAs($admin)
            ->postJson("/technician-incidents/{$incident->id}/messages", [
                'body' => 'Follow-up note',
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Follow-up note');

        $this->actingAs($admin)
            ->get("/technicians/{$technician->id}/edit?tab=incidents&incident={$incident->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Technicians/Edit')
                ->where('initialTab', 'incidents')
                ->where('selectedIncidentId', $incident->id));
    }

    public function test_admin_can_create_technician_incident_like_legacy(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $assignee = User::factory()->create();

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);
        $this->attachToCompany($assignee, $owner);

        $type = TechnicianIncidentType::query()->create([
            'name' => 'Feedback',
            'due_days' => 3,
            'send_mail_to_technician' => false,
        ]);

        $status = TechnicianIncidentStatus::query()->create([
            'name' => 'Abierta',
            'is_open' => true,
            'lifecycle' => 1,
            'is_default' => true,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $this->actingAs($admin)
            ->get('/technician-incidents/create?technician_id='.$technician->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TechnicianIncidents/Create')
                ->where('defaultTechnicianId', $technician->id)
                ->has('typeOptions')
                ->has('userOptions')
                ->has('technicianOptions'));

        $this->actingAs($admin)
            ->post('/technician-incidents', [
                'technician_id' => $technician->id,
                'technician_incident_type_id' => $type->id,
                'responded_by_id' => $assignee->id,
                'incident_text' => 'Technician arrived late',
            ])
            ->assertRedirect();

        $incident = TechnicianIncident::query()->where('technician_id', $technician->id)->first();

        $this->assertNotNull($incident);
        $this->assertSame((int) $status->id, (int) $incident->status_id);
        $this->assertSame($admin->id, (int) $incident->requested_by_id);
        $this->assertSame($assignee->id, (int) $incident->responded_by_id);
        $this->assertSame('Technician arrived late', $incident->incident_text);
        $this->assertNotNull($incident->due_at);
        $this->assertTrue($incident->due_at->isSameDay(now()->addDays(3)));
    }

    public function test_admin_can_verify_technician_incident(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = new TechnicianIncidentType;
        $type->forceFill([
            'id' => 2,
            'name' => 'Negociación',
            'due_days' => 5,
            'send_mail_to_technician' => false,
        ])->save();

        $open = TechnicianIncidentStatus::query()->create([
            'name' => 'Abierta',
            'is_open' => true,
            'lifecycle' => 1,
            'is_default' => true,
        ]);

        $verified = TechnicianIncidentStatus::query()->create([
            'name' => 'Verificada',
            'is_open' => false,
            'lifecycle' => 4,
            'marks_verified' => true,
            'sets_response_date' => true,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $incident = TechnicianIncident::query()->create([
            'status_id' => $open->id,
            'technician_incident_type_id' => 2,
            'incident_text' => 'Price negotiation',
            'technician_id' => $technician->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);

        $this->actingAs($admin)
            ->postJson("/technician-incidents/{$incident->id}/verify", [
                'response_text' => 'Agreed 10% discount',
                'negotiation_succeeded' => false,
                'unsuccessful_negotiation_solution' => 'Escalated to manager',
            ])
            ->assertOk()
            ->assertJsonPath('incident.is_verified', true)
            ->assertJsonPath('incident.verified_by_id', $admin->id)
            ->assertJsonPath('incident.status_id', $verified->id)
            ->assertJsonPath('incident.negotiation_succeeded', false)
            ->assertJsonPath('incident.response_text', 'Agreed 10% discount')
            ->assertJsonPath('incident.unsuccessful_negotiation_solution', 'Escalated to manager')
            ->assertJsonPath('incident.responded_at', now()->toDateString());

        $this->assertDatabaseHas('technician_incidents', [
            'id' => $incident->id,
            'is_verified' => true,
            'verified_by_id' => $admin->id,
            'status_id' => $verified->id,
            'responded_at' => now()->toDateString(),
        ]);
    }

    public function test_admin_can_change_technician_incident_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = TechnicianIncidentType::query()->create([
            'name' => 'Feedback',
            'due_days' => 1,
            'send_mail_to_technician' => false,
        ]);

        $open = TechnicianIncidentStatus::query()->create([
            'name' => 'Abierta',
            'is_open' => true,
            'lifecycle' => 1,
            'is_default' => true,
        ]);

        $inProgress = TechnicianIncidentStatus::query()->create([
            'name' => 'En Progreso',
            'is_open' => true,
            'lifecycle' => 2,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $incident = TechnicianIncident::query()->create([
            'status_id' => $open->id,
            'technician_incident_type_id' => $type->id,
            'incident_text' => 'Needs follow-up',
            'technician_id' => $technician->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);

        $this->actingAs($admin)
            ->getJson("/technician-incidents/{$incident->id}")
            ->assertOk()
            ->assertJsonPath('incident.status_id', $open->id)
            ->assertJsonFragment(['id' => $inProgress->id, 'label' => 'En Progreso']);

        $this->actingAs($admin)
            ->patchJson("/technician-incidents/{$incident->id}/status", [
                'status_id' => $inProgress->id,
            ])
            ->assertOk()
            ->assertJsonPath('incident.status_id', $inProgress->id)
            ->assertJsonPath('incident.status_name', 'En Progreso');

        $this->assertDatabaseHas('technician_incidents', [
            'id' => $incident->id,
            'status_id' => $inProgress->id,
        ]);

        $this->assertDatabaseHas('status_change_histories', [
            'document_type' => 'technician_incident',
            'document_id' => $incident->id,
            'old_status_id' => $open->id,
            'new_status_id' => $inProgress->id,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('technician_chat_messages', [
            'type' => 'system',
            'body' => 'sistema.global.cambio_estado',
        ]);

        $this->assertDatabaseMissing('technician_incident_messages', [
            'technician_incident_id' => $incident->id,
            'type' => 'system',
        ]);
    }

    public function test_leaving_verified_status_clears_verification_so_it_can_be_verified_again(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = TechnicianIncidentType::query()->create([
            'name' => 'Feedback',
            'due_days' => 1,
            'send_mail_to_technician' => false,
        ]);

        $open = TechnicianIncidentStatus::query()->create([
            'name' => 'Abierta',
            'is_open' => true,
            'lifecycle' => 1,
            'is_default' => true,
        ]);

        $verified = TechnicianIncidentStatus::query()->create([
            'name' => 'Verificada',
            'is_open' => false,
            'lifecycle' => 4,
            'marks_verified' => true,
            'sets_response_date' => true,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $incident = TechnicianIncident::query()->create([
            'status_id' => $open->id,
            'technician_incident_type_id' => $type->id,
            'incident_text' => 'Needs verification rollback',
            'technician_id' => $technician->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);

        $this->actingAs($admin)
            ->postJson("/technician-incidents/{$incident->id}/verify", [
                'response_text' => 'Resolved',
            ])
            ->assertOk()
            ->assertJsonPath('incident.is_verified', true)
            ->assertJsonPath('incident.status_id', $verified->id);

        $this->actingAs($admin)
            ->patchJson("/technician-incidents/{$incident->id}/status", [
                'status_id' => $open->id,
            ])
            ->assertOk()
            ->assertJsonPath('incident.status_id', $open->id)
            ->assertJsonPath('incident.is_verified', false)
            ->assertJsonPath('incident.verified_by_id', null)
            ->assertJsonPath('incident.verified_at', null);

        $this->assertDatabaseHas('technician_incidents', [
            'id' => $incident->id,
            'status_id' => $open->id,
            'is_verified' => false,
            'verified_by_id' => null,
            'verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson("/technician-incidents/{$incident->id}/verify", [
                'response_text' => 'Resolved again',
            ])
            ->assertOk()
            ->assertJsonPath('incident.is_verified', true)
            ->assertJsonPath('incident.status_id', $verified->id);
    }

    public function test_entering_verified_status_stamps_verification_metadata(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = TechnicianIncidentType::query()->create([
            'name' => 'Feedback',
            'due_days' => 1,
            'send_mail_to_technician' => false,
        ]);

        $open = TechnicianIncidentStatus::query()->create([
            'name' => 'Abierta',
            'is_open' => true,
            'lifecycle' => 1,
            'is_default' => true,
        ]);

        $verified = TechnicianIncidentStatus::query()->create([
            'name' => 'Verificada',
            'is_open' => false,
            'lifecycle' => 4,
            'marks_verified' => true,
            'sets_response_date' => true,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $incident = TechnicianIncident::query()->create([
            'status_id' => $open->id,
            'technician_incident_type_id' => $type->id,
            'incident_text' => 'Mark verified via status',
            'technician_id' => $technician->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);

        $this->actingAs($admin)
            ->patchJson("/technician-incidents/{$incident->id}/status", [
                'status_id' => $verified->id,
            ])
            ->assertOk()
            ->assertJsonPath('incident.status_id', $verified->id)
            ->assertJsonPath('incident.is_verified', true)
            ->assertJsonPath('incident.verified_by_id', $admin->id)
            ->assertJsonPath('incident.responded_at', now()->toDateString());

        $this->assertNotNull($incident->fresh()?->verified_at);
        $this->assertNotNull($incident->fresh()?->responded_at);
    }

    public function test_admin_can_post_rich_text_and_file_messages(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = TechnicianIncidentType::query()->create([
            'name' => 'Feedback',
            'due_days' => 1,
            'send_mail_to_technician' => false,
        ]);
        $status = TechnicianIncidentStatus::query()->create([
            'name' => 'Open',
            'is_open' => true,
            'lifecycle' => 1,
        ]);

        $technician = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $incident = TechnicianIncident::query()->create([
            'status_id' => $status->id,
            'technician_incident_type_id' => $type->id,
            'incident_text' => 'Needs docs',
            'technician_id' => $technician->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);

        $this->actingAs($admin)
            ->postJson("/technician-incidents/{$incident->id}/messages", [
                'body' => '<p>Hello <strong>world</strong></p>',
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', '<p>Hello <strong>world</strong></p>')
            ->assertJsonPath('message.type', 'text');

        $file = UploadedFile::fake()->image('photo.png');

        $this->actingAs($admin)
            ->post("/technician-incidents/{$incident->id}/messages", [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->assertJsonPath('message.type', 'image')
            ->assertJsonPath('message.download_name', 'photo.png');

        $attachment = TechnicianIncidentMessage::query()
            ->where('technician_incident_id', $incident->id)
            ->where('type', 'image')
            ->firstOrFail();

        $this->assertNotNull($attachment->attachment_path);
        Storage::disk('local')->assertExists($attachment->attachment_path);

        $this->actingAs($admin)
            ->get(route('technician-incidents.messages.file', [$incident, $attachment]))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson("/technician-incidents/{$incident->id}")
            ->assertOk()
            ->assertJsonPath('messages.1.type', 'image')
            ->assertJsonPath('messages.1.preview_url', route('technician-incidents.messages.file', [
                $incident,
                $attachment,
            ]));
    }

    public function test_incidents_are_scoped_to_active_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $this->attachToCompany($admin, $companyA);
        $this->attachToCompany($admin, $companyB, active: false);

        $type = TechnicianIncidentType::query()->create([
            'name' => 'Feedback',
            'due_days' => 1,
            'send_mail_to_technician' => false,
        ]);
        $status = TechnicianIncidentStatus::query()->create([
            'name' => 'Open',
            'is_open' => true,
            'lifecycle' => 1,
        ]);

        $technicianA = CompanyRelationship::factory()->create([
            'owner_company_id' => $companyA->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $technicianB = CompanyRelationship::factory()->create([
            'owner_company_id' => $companyB->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $incidentA = TechnicianIncident::query()->create([
            'status_id' => $status->id,
            'technician_incident_type_id' => $type->id,
            'incident_text' => 'Company A incident',
            'technician_id' => $technicianA->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);
        $incidentB = TechnicianIncident::query()->create([
            'status_id' => $status->id,
            'technician_incident_type_id' => $type->id,
            'incident_text' => 'Company B incident',
            'technician_id' => $technicianB->id,
            'requested_by_id' => $admin->id,
            'is_verified' => false,
        ]);

        $this->actingAs($admin)
            ->getJson('/technician-incidents/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $incidentA->id)
            ->assertJsonMissing(['id' => $incidentB->id]);

        $this->actingAs($admin)
            ->getJson("/technicians/{$technicianA->id}/incidents/data")
            ->assertOk()
            ->assertJsonPath('data.0.id', $incidentA->id);

        $this->actingAs($admin)
            ->getJson("/technicians/{$technicianB->id}/incidents/data")
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson("/technician-incidents/{$incidentB->id}")
            ->assertForbidden();

        $admin->forceFill(['active_company_id' => $companyB->id])->save();

        $this->actingAs($admin->fresh())
            ->getJson('/technician-incidents/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $incidentB->id)
            ->assertJsonMissing(['id' => $incidentA->id]);

        $this->actingAs($admin->fresh())
            ->getJson("/technician-incidents/{$incidentA->id}")
            ->assertForbidden();

        $this->actingAs($admin->fresh())
            ->getJson("/technicians/{$technicianA->id}/incidents/data")
            ->assertForbidden();
    }
}
