<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Optima paises catalog (PaisEnum).
 * Row values preserved as-is; table renamed to countries.
 * zona_horaria_id → timezone_id; geo_zone_id omitted.
 */
final class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/countries.php');

        if (! is_readable($path)) {
            throw new RuntimeException("Country data file not found or unreadable: {$path}");
        }

        /** @var list<array{id: int, name: string, iso_code: string|null, timezone_id: int|null}> $rows */
        $rows = require $path;

        foreach ($rows as $row) {
            Country::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'iso_code' => $row['iso_code'],
                    'timezone_id' => $row['timezone_id'],
                ],
            );
        }
    }
}
