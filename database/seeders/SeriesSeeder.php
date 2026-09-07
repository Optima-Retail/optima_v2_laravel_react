<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Series;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Optima series catalog (SerieEnum).
 * clave → key, seleccionable → is_selectable, serie_abono_id → credit_note_series_id.
 */
final class SeriesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/series.php');

        if (! is_readable($path)) {
            throw new RuntimeException("Series data file not found or unreadable: {$path}");
        }

        /** @var list<array{id: int, key: string, color: string, is_selectable: bool, credit_note_series_id: int|null}> $rows */
        $rows = require $path;

        foreach ($rows as $row) {
            Series::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'key' => $row['key'],
                    'color' => $row['color'],
                    'is_selectable' => $row['is_selectable'],
                    'credit_note_series_id' => null,
                ],
            );
        }

        foreach ($rows as $row) {
            Series::query()->whereKey($row['id'])->update([
                'credit_note_series_id' => $row['credit_note_series_id'],
            ]);
        }
    }
}
