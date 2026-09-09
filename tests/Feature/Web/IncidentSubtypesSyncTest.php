<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\IncidentSubtype;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\IncidentPrioritySeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IncidentSubtypesSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(IncidentPrioritySeeder::class);
        $this->seed(IncidentTypeSeeder::class);
    }

    public function test_admin_can_list_and_sync_subtypes_for_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $type = IncidentType::query()->findOrFail(11);

        $this->actingAs($admin)
            ->getJson("/config/incident-types/{$type->id}/subtypes")
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->actingAs($admin)
            ->putJson("/config/incident-types/{$type->id}/subtypes", [
                'subtypes' => [
                    ['id' => null, 'name' => 'Nueva petición'],
                    ['id' => null, 'name' => 'Presupuesto Elevado'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('incident_subtypes', [
            'name' => 'Nueva petición',
            'incident_type_id' => 11,
        ]);

        $kept = IncidentSubtype::query()
            ->where('incident_type_id', 11)
            ->where('name', 'Nueva petición')
            ->firstOrFail();

        $this->actingAs($admin)
            ->putJson("/config/incident-types/{$type->id}/subtypes", [
                'subtypes' => [
                    ['id' => $kept->id, 'name' => 'Nueva petición (updated)'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nueva petición (updated)');

        $this->assertSoftDeleted('incident_subtypes', [
            'name' => 'Presupuesto Elevado',
            'incident_type_id' => 11,
        ]);
    }

    public function test_user_without_permission_cannot_view_subtypes(): void
    {
        $user = User::factory()->create();
        $type = IncidentType::query()->findOrFail(11);

        $this->actingAs($user)
            ->getJson("/config/incident-types/{$type->id}/subtypes")
            ->assertForbidden();
    }
}
