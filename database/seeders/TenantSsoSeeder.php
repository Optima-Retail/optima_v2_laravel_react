<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SsoProvider;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Legacy tenants + proveedores_sso catalogs (optimaback SSO).
 */
final class TenantSsoSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'hermes'],
        );

        SsoProvider::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Azure',
                'slug' => 'azure',
                'driver' => 'azure',
            ],
        );
    }
}
