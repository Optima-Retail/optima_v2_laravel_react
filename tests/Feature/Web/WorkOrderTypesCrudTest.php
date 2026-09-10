<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\WorkOrderType;
use App\Models\WorkOrderTypeServiceType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WorkOrderTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_work_order_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/work-order-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/WorkOrderTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('workOrderTypes'));

        $this->actingAs($admin)
            ->post('/config/work-order-types', [
                'name' => 'Electricity',
                'code' => 'T07',
                'color' => '#FFE897',
            ])
            ->assertRedirect(route('config.work-order-types.index'))
            ->assertSessionHas('success', 'work_order_type_created_successfully');

        $type = WorkOrderType::query()->where('code', 'T07')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/work-order-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.code', 'T07');

        $this->actingAs($admin)
            ->getJson('/config/work-order-types/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Electricity',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id);

        $this->actingAs($admin)
            ->put("/config/work-order-types/{$type->id}", [
                'name' => 'Electrical',
                'code' => 'T07',
                'color' => '#FFE897',
            ])
            ->assertRedirect(route('config.work-order-types.index'))
            ->assertSessionHas('success', 'work_order_type_updated_successfully');

        $this->assertDatabaseHas('work_order_types', [
            'id' => $type->id,
            'name' => 'Electrical',
        ]);

        $this->actingAs($admin)
            ->delete("/config/work-order-types/{$type->id}")
            ->assertRedirect(route('config.work-order-types.index'))
            ->assertSessionHas('success', 'work_order_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_admin_can_sync_service_types_on_work_order_type_edit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $type = WorkOrderType::query()->create([
            'name' => 'Corrective',
            'code' => 'T01',
            'color' => '#2563eb',
        ]);

        $electric = ServiceType::query()->create([
            'name' => 'Electricidad',
            'code' => 'ELEC',
            'color' => '#5e5126',
        ]);
        $plumbing = ServiceType::query()->create([
            'name' => 'Fontaneria',
            'code' => 'FONT',
            'color' => '#be4d25',
        ]);

        $this->actingAs($admin)
            ->get("/config/work-order-types/{$type->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/WorkOrderTypes/Edit')
                ->where('workOrderType.id', $type->id)
                ->where('workOrderType.service_type_ids', [])
                ->has('serviceTypeOptions'));

        $this->actingAs($admin)
            ->put("/config/work-order-types/{$type->id}", [
                'name' => 'Corrective',
                'code' => 'T01',
                'color' => '#2563eb',
                'service_type_ids' => [$electric->id, $plumbing->id],
            ])
            ->assertRedirect(route('config.work-order-types.index'))
            ->assertSessionHas('success', 'work_order_type_updated_successfully');

        $this->assertDatabaseHas('work_order_type_service_types', [
            'work_order_type_id' => $type->id,
            'service_type_id' => $electric->id,
        ]);
        $this->assertDatabaseHas('work_order_type_service_types', [
            'work_order_type_id' => $type->id,
            'service_type_id' => $plumbing->id,
        ]);
        $this->assertSame(2, WorkOrderTypeServiceType::query()->where('work_order_type_id', $type->id)->count());

        $this->actingAs($admin)
            ->get("/config/work-order-types/{$type->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/WorkOrderTypes/Edit')
                ->where('workOrderType.service_type_ids', [$electric->id, $plumbing->id]));

        $this->actingAs($admin)
            ->put("/config/work-order-types/{$type->id}", [
                'name' => 'Corrective',
                'code' => 'T01',
                'color' => '#2563eb',
                'service_type_ids' => [$electric->id],
            ])
            ->assertRedirect(route('config.work-order-types.index'));

        $this->assertDatabaseHas('work_order_type_service_types', [
            'work_order_type_id' => $type->id,
            'service_type_id' => $electric->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('work_order_type_service_types', [
            'work_order_type_id' => $type->id,
            'service_type_id' => $plumbing->id,
        ]);
        $this->assertSame(1, WorkOrderTypeServiceType::query()->where('work_order_type_id', $type->id)->count());
    }

    public function test_user_without_permission_cannot_view_work_order_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/work-order-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/work-order-types/data')
            ->assertForbidden();
    }
}
