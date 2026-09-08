<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Config\FieldHelps\Services\FieldHelpService;
use App\Models\FieldHelp;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FieldHelpResolveTest extends TestCase
{
    use RefreshDatabase;

    private FieldHelpService $fieldHelps;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->fieldHelps = app(FieldHelpService::class);
    }

    public function test_guest_cannot_resolve_field_help(): void
    {
        $this->getJson('/field-help?'.http_build_query([
            'keys' => ['companies.tax_id'],
        ]))->assertUnauthorized();
    }

    public function test_authenticated_user_receives_requested_locale_content(): void
    {
        $this->seedHelp();

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::User->value);

        $response = $this->actingAs($user)
            ->getJson('/field-help?'.http_build_query([
                'keys' => ['companies.tax_id', 'companies.legal_name'],
                'locale' => 'es',
            ]))
            ->assertOk();

        $response->assertJsonPath('locale', 'es');

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame('NIF / CIF', $data['companies.tax_id']['title'] ?? null);
        $this->assertSame('Razón social', $data['companies.legal_name']['title'] ?? null);
        $this->assertArrayNotHasKey('companies.email', $data);
    }

    public function test_inactive_help_is_not_returned(): void
    {
        $this->fieldHelps->create([
            'key' => 'companies.tax_id',
            'is_active' => false,
            'translations' => [
                'en' => [
                    'title' => 'Tax ID',
                    'description' => 'Hidden',
                ],
            ],
        ]);

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::User->value);

        $this->actingAs($user)
            ->getJson('/field-help?'.http_build_query([
                'keys' => ['companies.tax_id'],
                'locale' => 'en',
            ]))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_unknown_keys_are_omitted(): void
    {
        $this->seedHelp();

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::User->value);

        $response = $this->actingAs($user)
            ->getJson('/field-help?'.http_build_query([
                'keys' => ['companies.tax_id', 'companies.does_not_exist'],
                'locale' => 'en',
            ]))
            ->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame('Tax ID', $data['companies.tax_id']['title'] ?? null);
        $this->assertArrayNotHasKey('companies.does_not_exist', $data);
    }

    public function test_fallback_locale_is_used_when_translation_missing(): void
    {
        $this->fieldHelps->create([
            'key' => 'companies.tax_id',
            'translations' => [
                'en' => [
                    'title' => 'Tax ID',
                    'description' => 'English only help.',
                ],
            ],
        ]);

        config(['app.fallback_locale' => 'en']);

        $user = User::factory()->create();
        $user->assignRole(RoleEnum::User->value);

        $response = $this->actingAs($user)
            ->getJson('/field-help?'.http_build_query([
                'keys' => ['companies.tax_id'],
                'locale' => 'es',
            ]))
            ->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame('Tax ID', $data['companies.tax_id']['title'] ?? null);
        $this->assertSame('English only help.', $data['companies.tax_id']['description'] ?? null);
    }

    public function test_unique_key_constraint_on_create(): void
    {
        $this->seedHelp();

        $this->expectException(QueryException::class);

        FieldHelp::query()->create([
            'key' => 'companies.tax_id',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_bulk_resolve_does_not_n_plus_one(): void
    {
        foreach (['companies.tax_id', 'companies.legal_name', 'companies.trade_name', 'companies.email', 'companies.phone'] as $index => $key) {
            $this->fieldHelps->create([
                'key' => $key,
                'sort_order' => $index,
                'translations' => [
                    'en' => [
                        'title' => $key,
                        'description' => "Help for {$key}",
                    ],
                    'es' => [
                        'title' => $key,
                        'description' => "Ayuda para {$key}",
                    ],
                ],
            ]);
        }

        $this->fieldHelps->forgetCache();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = $this->fieldHelps->getForKeys([
            'companies.tax_id',
            'companies.legal_name',
            'companies.trade_name',
            'companies.email',
            'companies.phone',
        ], 'es');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(5, $result);
        $this->assertLessThanOrEqual(3, count($queries));
    }

    public function test_cache_invalidates_when_help_is_updated(): void
    {
        $help = $this->fieldHelps->create([
            'key' => 'companies.tax_id',
            'translations' => [
                'en' => [
                    'title' => 'Tax ID',
                    'description' => 'Original',
                ],
            ],
        ]);

        $this->assertSame('Original', $this->fieldHelps->getForKeys(['companies.tax_id'], 'en')['companies.tax_id']['description']);

        $this->fieldHelps->update($help, [
            'translations' => [
                'en' => [
                    'title' => 'Tax ID',
                    'description' => 'Updated',
                ],
            ],
        ]);

        $this->assertSame('Updated', $this->fieldHelps->getForKeys(['companies.tax_id'], 'en')['companies.tax_id']['description']);
    }

    private function seedHelp(): void
    {
        $this->fieldHelps->create([
            'key' => 'companies.tax_id',
            'translations' => [
                'en' => [
                    'title' => 'Tax ID',
                    'description' => 'Company tax identification number.',
                ],
                'es' => [
                    'title' => 'NIF / CIF',
                    'description' => 'Identificación fiscal de la empresa.',
                ],
            ],
        ]);

        $this->fieldHelps->create([
            'key' => 'companies.legal_name',
            'translations' => [
                'en' => [
                    'title' => 'Legal name',
                    'description' => 'Registered legal name.',
                ],
                'es' => [
                    'title' => 'Razón social',
                    'description' => 'Nombre legal registrado.',
                ],
            ],
        ]);
    }
}
