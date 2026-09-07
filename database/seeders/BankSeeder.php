<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds Spanish MFI list from docs/lista-mfi-es.csv.
 *
 * CSV → banks mapping:
 * - NOMBRE → name, legal_name
 * - LEI → lei
 * - CÓDIGO DE SUPERVISOR → national_bank_code, supervisor_code
 * - CÓDIGO EUROPEO, CATEGORÍA, DIRECCIÓN, INFORME, ENTIDAD MATRIZ → unused (no matching columns)
 * - country_id → Spain (countries.id = 1)
 * - swift_bic, website → left empty
 */
final class BankSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('docs/lista-mfi-es.csv');

        if (! is_readable($path)) {
            throw new RuntimeException("Bank CSV not found or unreadable: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open bank CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('Bank CSV is empty.');
            }

            // Strip UTF-8 BOM and whitespace from headers.
            $header = array_map(
                static fn (mixed $column): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column) ?? (string) $column),
                $header,
            );

            $index = array_flip($header);
            $required = ['NOMBRE', 'LEI', 'CÓDIGO DE SUPERVISOR'];

            foreach ($required as $column) {
                if (! array_key_exists($column, $index)) {
                    throw new RuntimeException("Bank CSV missing required column: {$column}");
                }
            }

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }

                $name = trim((string) ($row[$index['NOMBRE']] ?? ''));
                $lei = trim((string) ($row[$index['LEI']] ?? ''));
                $supervisorCode = trim((string) ($row[$index['CÓDIGO DE SUPERVISOR']] ?? ''));

                if ($name === '' || $supervisorCode === '') {
                    continue;
                }

                Bank::query()->updateOrCreate(
                    [
                        'national_bank_code' => $supervisorCode,
                        'country_id' => 1,
                    ],
                    [
                        'name' => mb_substr($name, 0, 160),
                        'legal_name' => mb_substr($name, 0, 255),
                        'lei' => $lei !== '' ? mb_substr($lei, 0, 20) : null,
                        'supervisor_code' => mb_substr($supervisorCode, 0, 40),
                        'swift_bic' => null,
                        'website' => null,
                        'is_active' => true,
                    ],
                );
            }
        } finally {
            fclose($handle);
        }
    }
}
