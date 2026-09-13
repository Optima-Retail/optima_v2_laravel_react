<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusId;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\ServiceType;
use App\Models\TechnicianRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TechnicianRequestPrioritySeeder;
use Database\Seeders\TechnicianRequestStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class TechnicianRequestCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TechnicianRequestStatusSeeder::class);
        $this->seed(TechnicianRequestPrioritySeeder::class);
    }

    public function test_admin_can_create_request_with_service_types_and_priority_sets_due_at(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $serviceType = ServiceType::query()->create([
            'name' => 'Electricidad',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('technician-requests.store'), [
                'is_screening' => false,
                'description' => 'Need technician on site',
                'technician_request_priority_id' => 1,
                'service_type_ids' => [$serviceType->id],
                'responsible_user_id' => $admin->id,
            ]);

        $request = TechnicianRequest::query()->first();
        $this->assertNotNull($request);

        $response
            ->assertRedirect(route('technician-requests.edit', $request))
            ->assertSessionHas('success', 'technician_request_created_successfully');

        $this->assertSame($owner->id, $request->company_id);
        $this->assertFalse($request->is_screening);
        $this->assertSame(TechnicianRequestStatusId::RequestOpen->value, (int) $request->technician_request_status_id);
        $this->assertSame($admin->id, (int) $request->requester_user_id);
        $this->assertNotNull($request->due_at);
        $this->assertTrue(
            $request->due_at->equalTo($request->created_at?->copy()->addHours(3))
            || $request->due_at->between(
                now()->addHours(2),
                now()->addHours(4),
            ),
        );
        $this->assertDatabaseHas('technician_request_service_type', [
            'technician_request_id' => $request->id,
            'service_type_id' => $serviceType->id,
        ]);
    }

    public function test_admin_can_create_screening_from_parent_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $serviceType = ServiceType::query()->create(['name' => 'Fontanería']);

        $parent = TechnicianRequest::query()->create([
            'company_id' => $owner->id,
            'is_screening' => false,
            'description' => 'Parent request',
            'city' => 'Madrid',
            'technician_request_priority_id' => 2,
            'technician_request_status_id' => TechnicianRequestStatusId::RequestOpen->value,
            'requester_user_id' => $admin->id,
            'responsible_user_id' => $admin->id,
        ]);
        $parent->serviceTypes()->sync([$serviceType->id]);

        $response = $this->actingAs($admin)
            ->post(route('technician-requests.screenings.store', $parent));

        $screening = TechnicianRequest::query()
            ->where('parent_technician_request_id', $parent->id)
            ->first();

        $this->assertNotNull($screening);

        $response
            ->assertRedirect(route('technician-requests.edit', $screening))
            ->assertSessionHas('success', 'technician_request_screening_created_successfully');

        $this->assertTrue($screening->is_screening);
        $this->assertSame(TechnicianRequestStatusId::ScreeningOpen->value, (int) $screening->technician_request_status_id);
        $this->assertSame('Madrid', $screening->city);
        $this->assertNull($screening->resolved_at);
        $this->assertNull($screening->next_action_at);
        $this->assertDatabaseHas('technician_request_service_type', [
            'technician_request_id' => $screening->id,
            'service_type_id' => $serviceType->id,
        ]);
    }

    public function test_attach_technician_finalizes_open_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $technicianCompany = Company::factory()->create();
        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $technicianCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $request = TechnicianRequest::query()->create([
            'company_id' => $owner->id,
            'is_screening' => false,
            'description' => 'Open request',
            'technician_request_status_id' => TechnicianRequestStatusId::RequestOpen->value,
            'technician_request_priority_id' => 3,
            'requester_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('technician-requests.technicians.attach', $request), [
                'company_relationship_id' => $relationship->id,
            ]);

        $response
            ->assertRedirect(route('technician-requests.edit', $request))
            ->assertSessionHas('success', 'technician_request_technician_attached_successfully');

        $request->refresh();

        $this->assertSame(TechnicianRequestStatusId::RequestFinished->value, (int) $request->technician_request_status_id);
        $this->assertNotNull($request->resolved_at);
        $this->assertDatabaseHas('technician_request_technician', [
            'technician_request_id' => $request->id,
            'company_relationship_id' => $relationship->id,
        ]);
    }

    public function test_company_scope_hides_other_company_requests(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $own = TechnicianRequest::query()->create([
            'company_id' => $owner->id,
            'is_screening' => false,
            'description' => 'Own request',
            'technician_request_status_id' => TechnicianRequestStatusId::RequestOpen->value,
            'technician_request_priority_id' => 4,
            'requester_user_id' => $admin->id,
        ]);

        $foreign = TechnicianRequest::query()->create([
            'company_id' => $other->id,
            'is_screening' => false,
            'description' => 'Foreign request',
            'technician_request_status_id' => TechnicianRequestStatusId::RequestOpen->value,
            'technician_request_priority_id' => 4,
            'requester_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('technician-requests.data'))
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonMissing(['id' => $foreign->id]);

        $this->actingAs($admin)
            ->get(route('technician-requests.edit', $foreign))
            ->assertForbidden();
    }
}
