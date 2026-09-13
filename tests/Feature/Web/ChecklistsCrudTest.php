<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Checklist;
use App\Models\User;
use App\Models\WorkOrderStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ChecklistsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_checklists_for_work_orders_and_estimates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $workOrderStatus = WorkOrderStatus::query()->create([
            'name' => 'En Progreso',
            'kind' => WorkOrderStage::WorkOrder,
            'color' => '#a9cef0',
            'lifecycle' => 3,
            'is_open' => true,
        ]);

        $estimateStatus = WorkOrderStatus::query()->create([
            'name' => 'Pendiente',
            'kind' => WorkOrderStage::Estimate,
            'color' => null,
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $this->actingAs($admin)
            ->get('/config/checklists')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Checklists/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/config/checklists', [
                'label' => 'Confirm technician arrival',
                'requires_validation' => true,
                'document_type' => 'work_order',
                'work_order_status_id' => $workOrderStatus->id,
                'sort_order' => 10,
            ])
            ->assertRedirect(route('config.checklists.index'))
            ->assertSessionHas('success', 'checklist_created_successfully');

        $checklist = Checklist::query()->where('label', 'Confirm technician arrival')->firstOrFail();

        $this->assertDatabaseHas('checklists', [
            'id' => $checklist->id,
            'document_type' => 'work_order',
            'work_order_status_id' => $workOrderStatus->id,
            'requires_validation' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->getJson('/config/checklists/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $checklist->id)
            ->assertJsonPath('data.0.status_label', 'En Progreso');

        $this->actingAs($admin)
            ->put("/config/checklists/{$checklist->id}", [
                'label' => 'Send estimate to client',
                'requires_validation' => false,
                'document_type' => 'estimate',
                'work_order_status_id' => $estimateStatus->id,
                'sort_order' => 5,
            ])
            ->assertRedirect(route('config.checklists.index'))
            ->assertSessionHas('success', 'checklist_updated_successfully');

        $this->assertDatabaseHas('checklists', [
            'id' => $checklist->id,
            'label' => 'Send estimate to client',
            'document_type' => 'estimate',
            'work_order_status_id' => $estimateStatus->id,
            'requires_validation' => false,
            'sort_order' => 5,
        ]);

        $this->actingAs($admin)
            ->delete("/config/checklists/{$checklist->id}")
            ->assertRedirect(route('config.checklists.index'))
            ->assertSessionHas('success', 'checklist_deleted_successfully');

        $this->assertSoftDeleted($checklist);
    }

    public function test_user_without_permission_cannot_view_checklists(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/checklists')
            ->assertForbidden();
    }
}
