<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Timezone;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Optima zonas_horarias catalog (ZonasHorariasEnum).
 * Row values preserved as-is; table renamed to timezones.
 */
final class TimezoneSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/timezones.php');

        if (! is_readable($path)) {
            throw new RuntimeException("Timezone data file not found or unreadable: {$path}");
        }

        /** @var list<array{id: int, name: string, timezone: string}> $rows */
        $rows = require $path;

        foreach ($rows as $row) {
            Timezone::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'timezone' => $row['timezone'],
                ],
            );
        }
    }
}
