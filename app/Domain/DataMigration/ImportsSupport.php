<?php

declare(strict_types=1);

namespace App\Domain\DataMigration;

use Illuminate\Support\Facades\Schema;
use Throwable;

trait ImportsSupport
{
    /** @var array<string, array<string, int>> */
    private array $idMaps = [];

    /** @var list<array<string, mixed>> */
    private array $pendingIdMaps = [];

    /** @var array<string, list<string>> */
    private array $destColumnCache = [];

    private function sourceHasTable(string $table): bool
    {
        return Schema::connection('legacy')->hasTable($table);
    }

    private function sourceFirstTable(string ...$names): ?string
    {
        foreach ($names as $name) {
            if ($this->sourceHasTable($name)) {
                return $name;
            }
        }

        return null;
    }

    private function destHasTable(string $table): bool
    {
        return Schema::connection('migration_destination')->hasTable($table);
    }

    private function sourceHasColumn(string $table, string $column): bool
    {
        return $this->sourceHasTable($table) && Schema::connection('legacy')->hasColumn($table, $column);
    }

    /**
     * @return list<string>
     */
    private function destColumns(string $table): array
    {
        if (! isset($this->destColumnCache[$table])) {
            $this->destColumnCache[$table] = Schema::connection('migration_destination')->getColumnListing($table);
        }

        return $this->destColumnCache[$table];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function filterDestColumns(string $table, array $row): array
    {
        $allowed = array_flip($this->destColumns($table));

        return array_intersect_key($row, $allowed);
    }

    private function mappedId(string $entity, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        return $this->idMaps[$entity][(string) $legacyId] ?? null;
    }

    private function mappedCount(string $entity): int
    {
        return count($this->idMaps[$entity] ?? []);
    }

    private function skipPhase(string $label, int $mapped, string $reason = 'already mapped'): void
    {
        $this->command->line("  {$label}: {$mapped} {$reason} — skip");
    }

    /**
     * Source ids that are not yet in migration_id_map for this entity.
     *
     * @return list<int|string>
     */
    private function unmappedSourceIds(mixed $query, string $entity, string $idColumn = 'id'): array
    {
        $mapped = $this->idMaps[$entity] ?? [];
        $todo = [];
        (clone $query)->orderBy($idColumn)->select([$idColumn])->chunkById($this->chunk, function ($rows) use (&$todo, $mapped, $idColumn): void {
            foreach ($rows as $row) {
                $id = $row->{$idColumn};
                if (! isset($mapped[(string) $id])) {
                    $todo[] = $id;
                }
            }
        }, $idColumn);

        return $todo;
    }

    private function rememberMapped(string $entity, mixed $legacyId, int $newId): void
    {
        $this->idMaps[$entity][(string) $legacyId] = $newId;
        if (! $this->execute) {
            return;
        }
        $this->pendingIdMaps[] = [
            'entity' => $entity,
            'legacy_id' => (string) $legacyId,
            'new_id' => $newId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (count($this->pendingIdMaps) >= $this->chunk) {
            $this->flushIdMaps();
        }
    }

    private function flushIdMaps(): void
    {
        if ($this->pendingIdMaps === [] || ! $this->execute) {
            $this->pendingIdMaps = [];

            return;
        }
        try {
            $this->dest->table('migration_id_map')->insertOrIgnore($this->pendingIdMaps);
        } catch (Throwable) {
            foreach ($this->pendingIdMaps as $row) {
                $this->writeMap('migration_id_map', $row);
            }
        }
        $this->pendingIdMaps = [];
    }

    private function nextDestId(string $table): int
    {
        $max = $this->dest->table($table)->max('id');

        return ((int) $max) + 1;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertFiltered(string $table, array $row): void
    {
        $this->dest->table($table)->insert($this->filterDestColumns($table, $row));
    }

    private function catalogExists(string $table, mixed $id): bool
    {
        if (! $id) {
            return false;
        }

        return isset($this->catalogIds[$table][(int) $id]);
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function clip(mixed $value, int $max, ?string $fallback = null): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return $fallback;
        }
        if (strlen($text) > $max) {
            return substr($text, 0, $max);
        }

        return $text;
    }

    private function emailsJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $parts = array_values(array_filter(array_map(fn ($item): string => trim((string) $item), $value)));

            return $parts === [] ? null : json_encode($parts);
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $parts = array_values(array_filter(array_map(fn ($item): string => trim((string) $item), $decoded)));

            return $parts === [] ? null : json_encode($parts);
        }
        $parts = preg_split('/[,;]+/', $raw) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));

        return $parts === [] ? null : json_encode($parts);
    }

    private function reloadCatalogIds(): void
    {
        $tables = [
            'countries', 'languages', 'timezones', 'delegations', 'series', 'establishment_types',
            'integrations', 'tenants', 'currencies', 'client_priorities', 'cost_centers',
            'incident_statuses', 'incident_types', 'incident_priorities', 'incident_subtypes',
            'technician_incident_statuses', 'technician_incident_types', 'work_order_types',
            'work_order_statuses', 'contract_statuses', 'evaluation_statuses', 'service_types',
            'global_service_types', 'payment_methods', 'payment_documents', 'expense_types',
            'other_expense_types', 'indirect_cost_types', 'form_types', 'form_statuses',
            'compliment_types',             'technician_request_statuses', 'technician_request_priorities',
            'technician_attendance_confirmation_types', 'work_order_technician_statuses',
            'actions', 'job_titles', 'articles', 'provinces',
        ];
        foreach ($tables as $table) {
            if (! $this->destHasTable($table)) {
                $this->catalogIds[$table] = [];

                continue;
            }
            $this->catalogIds[$table] = array_fill_keys(
                array_map('intval', $this->dest->table($table)->pluck('id')->all()),
                true,
            );
        }
        if ($this->destHasTable('teams')) {
            $this->teamsByCode = $this->dest->table('teams')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        }
    }
}
