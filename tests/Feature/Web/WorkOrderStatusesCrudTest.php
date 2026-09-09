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
                'color' => '#a9cef0',
                'lifecycle' => 3,
                'is_open' => true,
            ])
            ->assertRedirect(route('config.work-order-statuses.index'))
            ->assertSessionHas('success', 'work_order_status_created_successfully');

        $status = WorkOrderStatus::query()->where('name', 'In Progress')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/work-order-statuses/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $status->id)
            ->assertJsonPath('data.0.lifecycle', 3)
            ->assertJsonPath('data.0.is_open', true);

        $this->actingAs($admin)
            ->put("/config/work-order-statuses/{$status->id}", [
                'name' => 'In Progress Updated',
                'color' => '#a9cef0',
                'lifecycle' => 3,
                'is_open' => false,
            ])
            ->assertRedirect(route('config.work-order-statuses.index'))
            ->assertSessionHas('success', 'work_order_status_updated_successfully');

        $this->assertDatabaseHas('work_order_statuses', [
            'id' => $status->id,
            'name' => 'In Progress Updated',
            'is_open' => false,
        ]);

        $this->actingAs($admin)
            ->delete("/config/work-order-statuses/{$status->id}")
            ->assertRedirect(route('config.work-order-statuses.index'))
            ->assertSessionHas('success', 'work_order_status_deleted_successfully');

        $this->assertSoftDeleted($status);
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
