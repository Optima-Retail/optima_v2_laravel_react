<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\FormType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class FormTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_form_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/form-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/FormTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('formTypes'));

        $this->actingAs($admin)
            ->post('/config/form-types', [
                'name' => 'Correctivo',
            ])
            ->assertRedirect(route('config.form-types.index'))
            ->assertSessionHas('success', 'form_type_created_successfully');

        $type = FormType::query()->where('name', 'Correctivo')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/form-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $type->id)
            ->assertJsonPath('data.0.name', 'Correctivo');

        $this->actingAs($admin)
            ->put("/config/form-types/{$type->id}", [
                'name' => 'Corrective',
            ])
            ->assertRedirect(route('config.form-types.index'))
            ->assertSessionHas('success', 'form_type_updated_successfully');

        $this->assertDatabaseHas('form_types', [
            'id' => $type->id,
            'name' => 'Corrective',
        ]);

        $this->actingAs($admin)
            ->delete("/config/form-types/{$type->id}")
            ->assertRedirect(route('config.form-types.index'))
            ->assertSessionHas('success', 'form_type_deleted_successfully');

        $this->assertSoftDeleted($type);
    }

    public function test_user_without_permission_cannot_view_form_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/form-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/form-types/data')
            ->assertForbidden();
    }
}
