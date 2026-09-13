<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\ExpenseType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ExpenseTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_expense_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/expense-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/ExpenseTypes/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/config/expense-types', [
                'name' => 'Colaboradores test',
            ])
            ->assertRedirect(route('config.expense-types.index'))
            ->assertSessionHas('success', 'expense_type_created_successfully');

        $type = ExpenseType::query()->where('name', 'Colaboradores test')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/expense-types/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $type->id);

        $this->actingAs($admin)
            ->put("/config/expense-types/{$type->id}", [
                'name' => 'Colaboradores updated',
            ])
            ->assertRedirect(route('config.expense-types.index'))
            ->assertSessionHas('success', 'expense_type_updated_successfully');

        $this->assertDatabaseHas('expense_types', [
            'id' => $type->id,
            'name' => 'Colaboradores updated',
        ]);

        $this->actingAs($admin)
            ->delete("/config/expense-types/{$type->id}")
            ->assertRedirect(route('config.expense-types.index'))
            ->assertSessionHas('success', 'expense_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_expense_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/expense-types')
            ->assertForbidden();
    }
}
