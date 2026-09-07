<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Language;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class LanguagesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_languages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/languages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Languages/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('languages'));

        $this->actingAs($admin)
            ->post('/config/languages', [
                'name' => 'ESPAÑOL',
                'code' => 'es',
            ])
            ->assertRedirect(route('config.languages.index'))
            ->assertSessionHas('success', 'language_created_successfully');

        $language = Language::query()->where('code', 'es')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/languages/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $language->id)
            ->assertJsonPath('data.0.code', 'es');

        $this->actingAs($admin)
            ->getJson('/config/languages/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'ESPAÑOL',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $language->id);

        $this->actingAs($admin)
            ->put("/config/languages/{$language->id}", [
                'name' => 'Español',
                'code' => 'es',
            ])
            ->assertRedirect(route('config.languages.index'))
            ->assertSessionHas('success', 'language_updated_successfully');

        $this->assertDatabaseHas('languages', [
            'id' => $language->id,
            'name' => 'Español',
            'code' => 'es',
        ]);

        $this->actingAs($admin)
            ->delete("/config/languages/{$language->id}")
            ->assertRedirect(route('config.languages.index'))
            ->assertSessionHas('success', 'language_deleted_successfully');

        $this->assertSoftDeleted($language);
    }

    public function test_user_without_permission_cannot_view_languages(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/languages')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/languages/data')
            ->assertForbidden();
    }
}
