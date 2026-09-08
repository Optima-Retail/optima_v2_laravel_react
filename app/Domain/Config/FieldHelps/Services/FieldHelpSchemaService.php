<?php

declare(strict_types=1);

namespace App\Domain\Config\FieldHelps\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lists application tables/columns for field-help key selection.
 */
final class FieldHelpSchemaService
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @var list<string>
     */
    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'personal_access_tokens',
        'sessions',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    /**
     * @var list<string>
     */
    private const EXCLUDED_COLUMNS = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    /**
     * @return array{tables: list<string>, columns_by_table: array<string, list<string>>}
     */
    public function catalog(): array
    {
        return Cache::remember('field-help:schema-catalog', self::CACHE_TTL_SECONDS, function (): array {
            $tables = collect(Schema::getTableListing())
                ->map(fn (string $table): string => Str::afterLast($table, '.'))
                ->reject(fn (string $table): bool => $this->isExcludedTable($table))
                ->unique()
                ->sort()
                ->values()
                ->all();

            $columnsByTable = [];
            foreach ($tables as $table) {
                $columnsByTable[$table] = $this->columnsForTableUncached($table);
            }

            return [
                'tables' => $tables,
                'columns_by_table' => $columnsByTable,
            ];
        });
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return $this->catalog()['tables'];
    }

    /**
     * @return list<string>
     */
    public function columnsForTable(string $table): array
    {
        $table = strtolower(trim($table));
        $catalog = $this->catalog();

        return $catalog['columns_by_table'][$table] ?? [];
    }

    public function isValidTableColumn(string $table, string $column): bool
    {
        $table = strtolower(trim($table));
        $column = strtolower(trim($column));

        return in_array($column, $this->columnsForTable($table), true);
    }

    public function forgetCache(): void
    {
        Cache::forget('field-help:schema-catalog');
    }

    /**
     * @return list<string>
     */
    private function columnsForTableUncached(string $table): array
    {
        if ($table === '' || $this->isExcludedTable($table) || ! Schema::hasTable($table)) {
            return [];
        }

        return collect(Schema::getColumnListing($table))
            ->reject(fn (string $column): bool => in_array($column, self::EXCLUDED_COLUMNS, true))
            ->sort()
            ->values()
            ->all();
    }

    private function isExcludedTable(string $table): bool
    {
        if (in_array($table, self::EXCLUDED_TABLES, true)) {
            return true;
        }

        return str_starts_with($table, 'telescope_')
            || str_starts_with($table, 'pulse_');
    }
}
