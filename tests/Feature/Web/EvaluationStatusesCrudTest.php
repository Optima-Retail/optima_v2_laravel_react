<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\EvaluationStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EvaluationStatusesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_evaluation_statuses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/evaluation-statuses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/EvaluationStatuses/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('evaluationStatuses'));

        $this->actingAs($admin)
            ->post('/config/evaluation-statuses', [
                'name' => 'Draft',
                'color' => '#f6eac2',
                'lifecycle' => 1,
                'is_open' => true,
            ])
            ->assertRedirect(route('config.evaluation-statuses.index'))
            ->assertSessionHas('success', 'evaluation_status_created_successfully');

        $status = EvaluationStatus::query()->where('name', 'Draft')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/evaluation-statuses/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $status->id)
            ->assertJsonPath('data.0.lifecycle', 1)
            ->assertJsonPath('data.0.is_open', true);

        $this->actingAs($admin)
            ->put("/config/evaluation-statuses/{$status->id}", [
                'name' => 'Draft Updated',
                'color' => '#f6eac2',
                'lifecycle' => 1,
                'is_open' => false,
            ])
            ->assertRedirect(route('config.evaluation-statuses.index'))
            ->assertSessionHas('success', 'evaluation_status_updated_successfully');

        $this->assertDatabaseHas('evaluation_statuses', [
            'id' => $status->id,
            'name' => 'Draft Updated',
            'is_open' => false,
        ]);

        $this->actingAs($admin)
            ->delete("/config/evaluation-statuses/{$status->id}")
            ->assertRedirect(route('config.evaluation-statuses.index'))
            ->assertSessionHas('success', 'evaluation_status_deleted_successfully');

        $this->assertSoftDeleted($status);
    }

    public function test_user_without_permission_cannot_view_evaluation_statuses(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/evaluation-statuses')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/evaluation-statuses/data')
            ->assertForbidden();
    }
}
