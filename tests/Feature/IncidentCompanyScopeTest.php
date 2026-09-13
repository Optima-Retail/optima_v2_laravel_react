<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Company;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\IncidentPrioritySeeder;
use Database\Seeders\IncidentStatusSeeder;
use Database\Seeders\IncidentSubtypeSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class IncidentCompanyScopeTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(IncidentPrioritySeeder::class);
        $this->seed(IncidentStatusSeeder::class);
        $this->seed(IncidentTypeSeeder::class);
        $this->seed(IncidentSubtypeSeeder::class);
    }

    public function test_admin_sees_only_incidents_for_active_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = IncidentType::query()->findOrFail(12);
        $subtypeId = $type->subtypes()->value('id');
        $this->assertNotNull($subtypeId);

        $own = Incident::query()->create([
            'company_id' => $owner->id,
            'subject' => 'Own company incident',
            'incident_type_id' => $type->id,
            'incident_subtype_id' => $subtypeId,
            'incident_priority_id' => 2,
            'incident_status_id' => 89,
            'responsible_user_id' => $admin->id,
        ]);

        $foreign = Incident::query()->create([
            'company_id' => $other->id,
            'subject' => 'Other company incident',
            'incident_type_id' => $type->id,
            'incident_subtype_id' => $subtypeId,
            'incident_priority_id' => 2,
            'incident_status_id' => 89,
            'responsible_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('incidents.data'))
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonMissing(['id' => $foreign->id]);

        $this->actingAs($admin)
            ->get(route('incidents.edit', $foreign))
            ->assertForbidden();
    }

    public function test_store_assigns_active_company_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $type = IncidentType::query()->findOrFail(12);
        $subtypeId = $type->subtypes()->value('id');
        $this->assertNotNull($subtypeId);

        $response = $this->actingAs($admin)
            ->post(route('incidents.store'), [
                'subject' => 'Scoped incident',
                'incident_type_id' => $type->id,
                'incident_subtype_id' => $subtypeId,
                'incident_priority_id' => 2,
                'incident_status_id' => 89,
                'responsible_user_id' => $admin->id,
                'collaborator_ids' => [],
            ]);

        $incident = Incident::query()->first();
        $this->assertNotNull($incident);

        $response
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('success', 'incident_created_successfully');

        $this->assertSame($owner->id, $incident->company_id);
        $this->assertSame('Scoped incident', $incident->subject);
    }
}
