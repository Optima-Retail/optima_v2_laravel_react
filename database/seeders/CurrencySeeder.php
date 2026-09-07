<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Optima monedas catalog (MonedaEnum).
 * moneda → name; codigo_moneda/simbolo were identical → code only.
 */
final class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/currencies.php');

        if (! is_readable($path)) {
            throw new RuntimeException("Currency data file not found or unreadable: {$path}");
        }

        /** @var list<array{id: int, name: string, code: string}> $rows */
        $rows = require $path;

        foreach ($rows as $row) {
            Currency::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'code' => $row['code'],
                ],
            );
        }
    }
}
