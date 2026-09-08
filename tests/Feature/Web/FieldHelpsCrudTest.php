<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Config\FieldHelps\Services\FieldHelpSchemaService;
use App\Models\FieldHelp;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class FieldHelpsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_field_helps(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/field-helps')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/FieldHelps/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->get('/config/field-helps/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/FieldHelps/Create')
                ->has('schema.tables')
                ->has('schema.columns_by_table')
                ->has('locales'));

        $this->actingAs($admin)
            ->post('/config/field-helps', [
                'table' => 'companies',
                'column' => 'tax_id',
                'is_active' => true,
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'Tax ID',
                        'description' => 'Company tax id.',
                    ],
                    [
                        'locale' => 'es',
                        'title' => 'NIF / CIF',
                        'description' => 'Identificación fiscal.',
                    ],
                ],
            ])
            ->assertRedirect(route('config.field-helps.index'))
            ->assertSessionHas('success', 'field_help_created_successfully');

        $fieldHelp = FieldHelp::query()->where('key', 'companies.tax_id')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/field-helps/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $fieldHelp->id)
            ->assertJsonPath('data.0.key', 'companies.tax_id');

        $this->actingAs($admin)
            ->put("/config/field-helps/{$fieldHelp->id}", [
                'table' => 'companies',
                'column' => 'email',
                'is_active' => true,
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'Email',
                        'description' => 'Company email.',
                    ],
                    [
                        'locale' => 'es',
                        'title' => 'Correo',
                        'description' => 'Correo de la empresa.',
                    ],
                ],
            ])
            ->assertRedirect(route('config.field-helps.index'))
            ->assertSessionHas('success', 'field_help_updated_successfully');

        $this->assertDatabaseHas('field_helps', [
            'id' => $fieldHelp->id,
            'key' => 'companies.email',
        ]);

        $this->actingAs($admin)
            ->delete("/config/field-helps/{$fieldHelp->id}")
            ->assertRedirect(route('config.field-helps.index'))
            ->assertSessionHas('success', 'field_help_deleted_successfully');

        $this->assertDatabaseMissing('field_helps', ['id' => $fieldHelp->id]);
    }

    public function test_user_without_permission_cannot_view_field_helps(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/field-helps')
            ->assertForbidden();
    }

    public function test_create_rejects_invalid_table_column(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->post('/config/field-helps', [
                'table' => 'companies',
                'column' => 'not_a_real_column_xyz',
                'is_active' => true,
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'X',
                        'description' => 'Y',
                    ],
                ],
            ])
            ->assertSessionHasErrors('column');
    }

    public function test_schema_tables_are_unique(): void
    {
        /** @var FieldHelpSchemaService $schema */
        $schema = app(FieldHelpSchemaService::class);
        $schema->forgetCache();

        $tables = $schema->tables();

        $this->assertNotEmpty($tables);
        $this->assertSame($tables, array_values(array_unique($tables)));
    }
}
