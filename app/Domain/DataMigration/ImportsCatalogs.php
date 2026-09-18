<?php

declare(strict_types=1);

namespace App\Domain\DataMigration;

use Illuminate\Support\Facades\Schema;

trait ImportsCatalogs
{
    private function importCatalogs(): void
    {
        $this->copyCatalogById('zonas_horarias', 'timezones', function (object $row): array {
            return [
                'name' => $this->clip($row->name ?? null, 255, 'Timezone '.$row->id),
                'timezone' => $this->clip($row->timezone ?? null, 255, 'UTC'),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ];
        });

        $this->copyCatalogById('paises', 'countries', function (object $row): array {
            return [
                'name' => $this->clip($row->nombre, 255, 'Country '.$row->id),
                'iso_code' => $this->str($row->codigo_iso ?? null, 8),
                'timezone_id' => $this->catalog((int) ($row->zona_horaria_id ?? 0), 'timezones'),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('provincias', 'provinces', function (object $row): ?array {
            $countryId = $this->catalog((int) ($row->pais_id ?? 0), 'countries');
            if ($countryId === null) {
                return null;
            }

            return [
                'name' => $this->clip($row->nombre, 255, 'Province '.$row->id),
                'code' => $this->str($row->codigo ?? $row->code ?? null, 10),
                'country_id' => $countryId,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('idiomas', 'languages', function (object $row): array {
            return [
                'name' => $this->clip($row->nombre, 255, 'Language '.$row->id),
                'code' => $this->str($row->codigo ?? $row->iso_639_1 ?? null, 16) ?? 'xx'.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'code');

        $this->copyCatalogById('prioridades', 'client_priorities', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Priority '.$row->id,
                'code' => $this->str($row->clave ?? null, 64),
                'color' => $this->str($row->color ?? null, 32),
                'level' => $this->unsignedInt($row->nivel ?? null) ?? 3,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'code');

        $this->copyCatalogById('monedas', 'currencies', function (object $row): array {
            $code = $this->str($row->codigo_moneda ?? $row->simbolo ?? null, 64);

            return [
                'name' => $this->str($row->moneda ?? null, 255) ?? $code ?? 'Currency '.$row->id,
                'code' => $code ?? 'CUR'.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'code');

        $this->copyCatalogById('series', 'series', function (object $row): array {
            return [
                'key' => $this->str($row->clave ?? null, 64) ?? 'S'.$row->id,
                'color' => $this->str($row->color ?? null, 32) ?? '#ffffff',
                'is_selectable' => $this->bool($row->seleccionable ?? true),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'key');

        $this->copyCatalogById('centros_coste', 'cost_centers', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Cost center '.$row->id,
                'code' => $this->str($row->codigo ?? null, 64) ?? 'CC'.$row->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'code');

        $this->copyCatalogById('tipos_establecimiento', 'establishment_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Type '.$row->id,
                'code' => 'ET'.$row->id,
                'health_and_safety_delay_days' => $this->unsignedInt($row->dias_atraso_prl ?? null) ?? 1,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'code');

        $this->copyCatalogById('tecnicos_incidencias_tipos', 'technician_incident_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Type '.$row->id,
                'due_days' => $this->unsignedInt($row->dias_vencimiento ?? null) ?? 1,
                'send_mail_to_technician' => $this->bool($row->enviar_mail_tecnico ?? false),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ];
        });

        $this->copyCatalogById('incidencias_prioridades', 'incident_priorities', function (object $row): array {
            return [
                'name' => $this->clip($row->nombre, 255, 'Priority '.$row->id),
                'color' => $this->str($row->color ?? null, 32),
                'resolution_time_hours' => $this->unsignedInt($row->tiempo_resolucion ?? $row->horas_resolucion ?? $row->horas ?? null) ?? 8,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $incidentTypes = $this->sourceFirstTable('tipos_incidencia', 'incidencias_tipos');
        if ($incidentTypes !== null) {
            $this->copyCatalogById($incidentTypes, 'incident_types', function (object $row): array {
                $priorityId = (int) ($row->prioridad_id ?? 2);
                if (! $this->catalogExists('incident_priorities', $priorityId)) {
                    $priorityId = $this->catalogExists('incident_priorities', 2) ? 2 : 1;
                }

                return [
                    'name' => $this->clip($row->nombre, 255, 'Type '.$row->id),
                    'color' => $this->str($row->color ?? null, 32) ?? '#FFFFFF',
                    'default_priority_id' => $this->catalogExists('incident_priorities', $priorityId) ? $priorityId : null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                    'deleted_at' => $row->deleted_at ?? null,
                ];
            });
        }

        $this->copyCatalogById('incidencia_qc_subtipos', 'incident_subtypes', function (object $row): ?array {
            $typeId = (int) ($row->tipo_id ?? $row->incidencia_tipo_id ?? 0);
            if ($typeId < 1 || ! $this->catalogExists('incident_types', $typeId)) {
                return null;
            }

            return [
                'name' => $this->clip($row->nombre, 255, 'Subtype '.$row->id),
                'incident_type_id' => $typeId,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('ots_tipos', 'work_order_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Type '.$row->id,
                'code' => $this->str($row->clave ?? $row->codigo ?? null, 64) ?? 'T'.$row->id,
                'color' => $this->str($row->color ?? null, 32),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'code');

        $this->copyCatalogById('tipos_servicios', 'service_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Service '.$row->id,
                'code' => $this->str($row->clave ?? $row->codigo ?? null, 64),
                'color' => $this->str($row->color ?? null, 32),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'code');

        $this->copyCatalogById('tipos_servicios_globales', 'global_service_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Service '.$row->id,
                'code' => $this->str($row->clave ?? $row->codigo ?? null, 64),
                'color' => $this->str($row->color ?? null, 32),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'code');

        $this->copyCatalogById('formas_de_pago', 'payment_methods', function (object $row): array {
            return [
                'name' => $this->str($row->nombre ?? $row->name ?? null, 255) ?? 'Method '.$row->id,
                'due_count' => $this->unsignedInt($row->vencimiento ?? $row->due_count ?? null),
                'days' => $this->unsignedInt($row->dias ?? $row->days ?? null),
                'code' => $this->str($row->codigo ?? $row->code ?? null, 64),
                'is_active' => 1,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('documentos_de_pago', 'payment_documents', function (object $row): array {
            return [
                'name' => $this->str($row->nombre ?? $row->name ?? null, 255) ?? 'Document '.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('tipos_de_gasto', 'expense_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Expense '.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('costes_indirectos_tipo', 'indirect_cost_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre ?? $row->name ?? null, 255) ?? 'Indirect '.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('formularios_tipos', 'form_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Form type '.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('formularios_estados', 'form_statuses', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Status '.$row->id,
                'next_status_id' => null,
                'is_active' => 1,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('tipos_felicitacion', 'compliment_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Type '.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('tecnicos_prioridades', 'technician_request_priorities', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Priority '.$row->id,
                'key' => $this->str($row->clave ?? $row->key ?? null, 32) ?? 'p'.$row->id,
                'color' => $this->str($row->color ?? null, 32),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        }, 'key');

        $this->copyCatalogById('tecnico_tipos_confirmacion_asistencia', 'technician_attendance_confirmation_types', function (object $row): array {
            return [
                'name' => $this->str($row->nombre, 255) ?? 'Type '.$row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        $this->copyCatalogById('acciones', 'actions', function (object $row): array {
            return [
                'weight_key' => $this->str($row->constante ?? $row->weight_key ?? null, 255) ?? 'action_'.$row->id,
            ];
        });

        $this->importChecklists();
        $this->importMissingTeams();
        $this->reloadCatalogIds();
    }

    /**
     * @param  callable(object): (?array<string, mixed>)  $mapper
     */
    private function copyCatalogById(string $source, string $dest, callable $mapper, ?string $uniqueColumn = null): void
    {
        if (! $this->sourceHasTable($source) || ! $this->destHasTable($dest)) {
            return;
        }

        $query = $this->legacy->table($source);
        if ($this->sourceHasColumn($source, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $rows = $query->orderBy('id')->get();
        $existingIds = array_fill_keys(array_map('intval', $this->dest->table($dest)->pluck('id')->all()), true);
        $pending = $rows->filter(fn ($row): bool => ! isset($existingIds[(int) $row->id]));
        if ($pending->isEmpty()) {
            $this->skipPhase('Catalog '.$dest, $rows->count());
            $this->counts['catalog_'.$dest] = 0;

            return;
        }

        $existingUniques = [];
        if ($uniqueColumn !== null) {
            foreach ($this->dest->table($dest)->pluck($uniqueColumn) as $value) {
                if ($value !== null && $value !== '') {
                    $existingUniques[strtolower((string) $value)] = true;
                }
            }
        }

        $bar = $this->startProgress('Catalog '.$dest.' (missing '.$pending->count().')', $pending->count());
        $inserted = 0;
        foreach ($pending as $row) {
            $bar?->advance();
            $id = (int) $row->id;
            $payload = $mapper($row);
            if ($payload === null) {
                continue;
            }
            if ($uniqueColumn !== null && isset($payload[$uniqueColumn]) && $payload[$uniqueColumn] !== null && $payload[$uniqueColumn] !== '') {
                $key = strtolower((string) $payload[$uniqueColumn]);
                if (isset($existingUniques[$key])) {
                    continue;
                }
                $existingUniques[$key] = true;
            }
            $payload['id'] = $id;
            if ($this->execute) {
                try {
                    $this->insertFiltered($dest, $payload);
                } catch (\Throwable $e) {
                    $this->quarantine($source, (string) $id, null, 'skipped', $e->getMessage(), null);

                    continue;
                }
            }
            $existingIds[$id] = true;
            $this->catalogIds[$dest][$id] = true;
            $inserted++;
        }
        $this->finishProgress($bar);
        $this->counts['catalog_'.$dest] = $inserted;
    }

    private function importChecklists(): void
    {
        if (! $this->sourceHasTable('checklists') || ! $this->destHasTable('checklists')) {
            return;
        }
        $query = $this->legacy->table('checklists');
        if ($this->sourceHasColumn('checklists', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $rows = $query->orderBy('id')->get();
        $existing = array_fill_keys(array_map('intval', $this->dest->table('checklists')->pluck('id')->all()), true);
        $pending = $rows->filter(fn ($row): bool => ! isset($existing[(int) $row->id]));
        if ($pending->isEmpty()) {
            $this->skipPhase('Catalog checklists', $rows->count());
            $this->counts['catalog_checklists'] = 0;

            return;
        }
        $bar = $this->startProgress('Catalog checklists (missing '.$pending->count().')', $pending->count());
        $inserted = 0;
        foreach ($pending as $row) {
            $bar?->advance();
            $id = (int) $row->id;
            $statusId = (int) ($row->estado_id ?? 0);
            if (! $this->catalogExists('work_order_statuses', $statusId)) {
                continue;
            }
            $modelo = (int) ($row->modelo_id ?? 0);
            $documentType = $modelo === 1 ? 'estimate' : 'work_order';
            $payload = [
                'id' => $id,
                'label' => $this->str($row->texto ?? $row->label ?? null, 65535) ?? 'Checklist '.$id,
                'requires_validation' => $this->bool($row->validar ?? true),
                'document_type' => $documentType,
                'work_order_status_id' => $statusId,
                'sort_order' => $this->unsignedInt($row->orden ?? null) ?? 0,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ];
            if ($this->execute) {
                try {
                    $this->insertFiltered('checklists', $payload);
                } catch (\Throwable $e) {
                    $this->quarantine('checklists', (string) $id, null, 'skipped', $e->getMessage(), null);

                    continue;
                }
            }
            $inserted++;
        }
        $this->finishProgress($bar);
        $this->counts['catalog_checklists'] = $inserted;
    }

    private function importMissingTeams(): void
    {
        if (! $this->sourceHasTable('equipos') || ! $this->destHasTable('teams')) {
            return;
        }
        $query = $this->legacy->table('equipos');
        if ($this->sourceHasColumn('equipos', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $rows = $query->orderBy('id')->get();
        $bar = $this->startProgress('Catalog teams (missing codes)', $rows->count());
        $inserted = 0;
        foreach ($rows as $row) {
            $bar?->advance();
            $code = $this->str((string) $row->id, 64);
            $padded = 'EQ'.str_pad((string) $row->id, 2, '0', STR_PAD_LEFT);
            $existing = $this->teamsByCode[$padded] ?? $this->teamsByCode[$code] ?? null;
            if ($code === null) {
                continue;
            }
            if ($existing !== null) {
                $this->teamsByCode[$code] = $existing;
                $this->teamsByCode[$padded] = $existing;

                continue;
            }
            $insertCode = isset($this->teamsByCode[$padded]) ? $code : $padded;
            if (! $this->execute) {
                $synthetic = $this->syntheticId++;
                $this->teamsByCode[$code] = $synthetic;
                $this->teamsByCode[$padded] = $synthetic;
                $inserted++;

                continue;
            }
            $newId = $this->dest->table('teams')->insertGetId($this->filterDestColumns('teams', [
                'code' => $insertCode,
                'name' => $this->clip($row->nombre, 255, $insertCode),
                'manager_id' => $this->mappedUser($row->manager_id ?? null),
                'controller_id' => $this->mappedUser($row->controller_id ?? null),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ]));
            $this->teamsByCode[$code] = $newId;
            $this->teamsByCode[$padded] = $newId;
            $inserted++;
        }
        $this->finishProgress($bar);
        $this->counts['catalog_teams'] = $inserted;
    }
}
