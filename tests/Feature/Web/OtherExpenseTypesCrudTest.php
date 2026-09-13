<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\OtherExpenseType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OtherExpenseTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_other_expense_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/other-expense-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/OtherExpenseTypes/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/config/other-expense-types', [
                'name' => 'Costes financieros',
            ])
            ->assertRedirect(route('config.other-expense-types.index'))
            ->assertSessionHas('success', 'other_expense_type_created_successfully');

        $type = OtherExpenseType::query()->where('name', 'Costes financieros')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/other-expense-types/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $type->id);

        $this->actingAs($admin)
            ->put("/config/other-expense-types/{$type->id}", [
                'name' => 'Costes financieros updated',
            ])
            ->assertRedirect(route('config.other-expense-types.index'))
            ->assertSessionHas('success', 'other_expense_type_updated_successfully');

        $this->assertDatabaseHas('other_expense_types', [
            'id' => $type->id,
            'name' => 'Costes financieros updated',
        ]);

        $this->actingAs($admin)
            ->delete("/config/other-expense-types/{$type->id}")
            ->assertRedirect(route('config.other-expense-types.index'))
            ->assertSessionHas('success', 'other_expense_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_other_expense_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/other-expense-types')
            ->assertForbidden();
    }
}
