<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            CompanySeeder::class,
            BrandSeeder::class,
            WorkOrderTypeSeeder::class,
            TeamSeeder::class,
            LanguageSeeder::class,
            IntegrationSeeder::class,
            RatingTypeSeeder::class,
            TimezoneSeeder::class,
            CountrySeeder::class,
            BankSeeder::class,
            SeriesSeeder::class,
            CurrencySeeder::class,
            DelegationSeeder::class,
            EstablishmentSeeder::class,
        ]);
    }
}
