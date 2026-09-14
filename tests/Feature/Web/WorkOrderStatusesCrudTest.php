<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\User;
use App\Models\WorkOrderStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WorkOrderStatusesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_work_order_statuses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/work-order-statuses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/WorkOrderStatuses/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('workOrderStatuses'));

        $this->actingAs($admin)
            ->post('/config/work-order-statuses', [
                'name' => 'In Progress',
                'kind' => 'work_order',
                'color' => '#a9cef0',
                'lifecycle' => 3,
                'is_open' => true,
                'is_default' => true,
                'confirms_estimate' => false,
                'rejects_to_estimate' => false,
                'is_post_confirm_default' => false,
                'sets_sent_at' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'work_order_status_created_successfully');

        $status = WorkOrderStatus::query()->where('name', 'In Progress')->firstOrFail();
        $this->assertTrue($status->is_default);

        $this->actingAs($admin)
            ->getJson('/config/work-order-statuses/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $status->id)
            ->assertJsonPath('data.0.kind', 'work_order')
            ->assertJsonPath('data.0.lifecycle', 3)
            ->assertJsonPath('data.0.is_open', true)
            ->assertJsonPath('data.0.is_default', true);

        $target = WorkOrderStatus::query()->create([
            'name' => 'Done',
            'kind' => 'work_order',
            'lifecycle' => 4,
            'is_open' => false,
        ]);

        $this->actingAs($admin)
            ->get("/config/work-order-statuses/{$status->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/WorkOrderStatuses/Edit')
                ->where('workOrderStatus.is_default', true)
                ->has('targetOptions'));

        $this->actingAs($admin)
            ->put("/config/work-order-statuses/{$status->id}", [
                'name' => 'In Progress Updated',
                'kind' => 'work_order',
                'color' => '#a9cef0',
                'lifecycle' => 3,
                'is_open' => false,
                'is_default' => true,
                'confirms_estimate' => false,
                'rejects_to_estimate' => false,
                'is_post_confirm_default' => true,
                'sets_sent_at' => false,
                'transitions' => [
                    [
                        'to_status_id' => $target->id,
                        'requires_confirmation' => true,
                        'requires_justification' => true,
                    ],
                ],
            ])
            ->assertRedirect(route('config.work-order-statuses.edit', $status))
            ->assertSessionHas('success', 'work_order_status_updated_successfully');

        $this->assertDatabaseHas('work_order_statuses', [
            'id' => $status->id,
            'name' => 'In Progress Updated',
            'kind' => 'work_order',
            'is_open' => false,
            'is_post_confirm_default' => true,
        ]);

        $this->assertDatabaseHas('work_order_status_transitions', [
            'from_status_id' => $status->id,
            'to_status_id' => $target->id,
            'requires_confirmation' => true,
            'requires_justification' => true,
        ]);

        $this->actingAs($admin)
            ->delete("/config/work-order-statuses/{$status->id}")
            ->assertRedirect(route('config.work-order-statuses.index'))
            ->assertSessionHas('success', 'work_order_status_deleted_successfully');

        $this->assertSoftDeleted($status);
    }

    public function test_default_flag_is_unique_per_kind(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->post('/config/work-order-statuses', [
                'name' => 'Open A',
                'kind' => 'work_order',
                'is_open' => true,
                'is_default' => true,
            ])
            ->assertRedirect();

        $first = WorkOrderStatus::query()->where('name', 'Open A')->firstOrFail();

        $this->actingAs($admin)
            ->post('/config/work-order-statuses', [
                'name' => 'Open B',
                'kind' => 'work_order',
                'is_open' => true,
                'is_default' => true,
            ])
            ->assertRedirect();

        $first->refresh();
        $second = WorkOrderStatus::query()->where('name', 'Open B')->firstOrFail();

        $this->assertFalse($first->is_default);
        $this->assertTrue($second->is_default);
        $this->assertSame(1, WorkOrderStatus::query()->where('kind', 'work_order')->where('is_default', true)->count());
    }

    public function test_user_without_permission_cannot_view_work_order_statuses(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/work-order-statuses')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/work-order-statuses/data')
            ->assertForbidden();
    }
}
