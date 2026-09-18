<?php

declare(strict_types=1);

namespace App\Domain\DataMigration;

use Illuminate\Support\Str;

trait ImportsExpanded
{
    private function importExpanded(int $orId): void
    {
        $this->importDelegations($orId);
        $this->importSuppliers($orId);
        $this->importBrandCollaborators();
        $this->importBrandMessages();
        $this->importVehicles();
        $this->importArticles();
        $this->importArticleLanguages();
        $this->importContracts();
        $this->importEvaluations();
        $this->importIncidents($orId);
        $this->importIncidentLines();
        $this->importTechnicianIncidents();
        $this->importTechnicianIncidentMessages();
        $this->importArticleClients();
        $this->importRequesters();
        $this->importWorkOrdersFromEstimates($orId);
        $this->importWorkOrdersFromOts($orId);
        $this->importWorkOrderLines();
        $this->importWorkOrderTechnicians();
        $this->importTechnicianRequests($orId);
        $this->importTechnicianRequestTechnicians();
        $this->importCompliments();
        $this->importCollaborators();
        $this->flushIdMaps();
        $this->reloadCatalogIds();
    }

    private function importDelegations(int $orId): void
    {
        if (! $this->sourceHasTable('delegaciones') || ! $this->destHasTable('delegations')) {
            return;
        }
        $query = $this->legacy->table('delegaciones');
        if ($this->sourceHasColumn('delegaciones', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $rows = $query->orderBy('id')->get();
        $existing = array_fill_keys(array_map('intval', $this->dest->table('delegations')->pluck('id')->all()), true);
        $bar = $this->startProgress('Delegations', $rows->count());
        $inserted = 0;
        foreach ($rows as $row) {
            $bar?->advance();
            $id = (int) $row->id;
            if (isset($existing[$id])) {
                $this->catalogIds['delegations'][$id] = true;

                continue;
            }
            $empresa = (string) ($row->empresa_id ?? 'OR');
            $companyId = $this->companyMap['empresas:'.$empresa] ?? $orId;
            $payload = [
                'id' => $id,
                'name' => $this->str($row->nombre, 255) ?? 'Delegation '.$id,
                'tax_id' => $this->str($row->nif ?? null, 64),
                'company_id' => $companyId,
                'address' => $this->text($row->direccion ?? null),
                'currency_id' => $this->catalog((int) ($row->moneda_id ?? 0), 'currencies'),
                'country_id' => $this->catalog((int) ($row->pais_id ?? 0), 'countries'),
                'series_id' => $this->catalog((int) ($row->serie_id ?? 0), 'series'),
                'cost_includes_vat' => $this->bool($row->iva_incluido_coste ?? false),
                'recovers_vat' => $this->bool($row->recupera_iva ?? true),
                'billing_info' => is_string($row->informacion_facturacion ?? null) ? $row->informacion_facturacion : null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
            if ($this->execute) {
                try {
                    $this->insertFiltered('delegations', $payload);
                } catch (\Throwable $e) {
                    $this->quarantine('delegaciones', (string) $id, null, 'skipped', $e->getMessage(), null);

                    continue;
                }
            }
            $this->catalogIds['delegations'][$id] = true;
            $inserted++;
        }
        $this->finishProgress($bar);
        $this->counts['delegations'] = $inserted;
    }

    private function importSuppliers(int $orId): void
    {
        if (! $this->sourceHasTable('proveedores')) {
            return;
        }
        $query = $this->legacy->table('proveedores');
        if ($this->sourceHasColumn('proveedores', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $rows = $query->orderBy('id')->get();
        $bar = $this->startProgress('Suppliers', $rows->count());
        $created = 0;
        foreach ($rows as $row) {
            $bar?->advance();
            if (isset($this->companyMap['proveedores:'.$row->id])) {
                $this->ensureSupplierRelationship($orId, (int) $row->id, $this->companyMap['proveedores:'.$row->id], $row);

                continue;
            }
            $nif = Nif::normalize($row->nif ?? null);
            $companyId = null;
            if ($nif !== null && isset($this->usedTaxIds[$nif]) && $this->usedTaxIds[$nif] > 1) {
                $companyId = $this->usedTaxIds[$nif];
            }
            if ($companyId === null && $this->execute && $nif !== null) {
                $found = $this->dest->table('companies')->where('tax_id', $nif)->where('kind', 'party')->value('id');
                $companyId = $found !== null ? (int) $found : null;
            }
            if ($companyId === null) {
                $created++;
                if (! $this->execute) {
                    $companyId = $this->syntheticId++;
                } else {
                    $taxId = $this->usableTaxId($nif, (string) $row->id, 'proveedores');
                    if ($taxId !== null && isset($this->usedTaxIds[$taxId])) {
                        $taxId = null;
                    }
                    $companyId = $this->dest->table('companies')->insertGetId($this->filterDestColumns('companies', [
                        'name' => $this->str($row->nombre_fiscal, 255) ?? $this->str($row->nombre_comercial, 255) ?? 'Supplier '.$row->id,
                        'tradename' => $this->str($row->nombre_comercial, 255),
                        'slug' => $this->uniqueSlug($this->str($row->nombre_fiscal, 255) ?? $this->str($row->nombre_comercial, 255) ?? 'supplier-'.$row->id),
                        'tax_id' => $taxId,
                        'kind' => 'party',
                        'country_id' => $this->catalog((int) ($row->pais_id ?? 0), 'countries'),
                        'email' => $this->str($row->correo ?? null, 255),
                        'phone' => $this->str($row->telefono ?? null, 50),
                        'address_line_1' => $this->str($row->direccion_1 ?? null, 255),
                        'address_line_2' => $this->str($row->direccion_2 ?? null, 255),
                        'city' => $this->str($row->poblacion ?? null, 255),
                        'postal_code' => $this->str($row->codigo_postal ?? null, 20),
                        'is_active' => $this->bool($row->activo ?? true),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]));
                    if ($taxId !== null) {
                        $this->usedTaxIds[$taxId] = $companyId;
                    }
                }
            }
            $this->companyMap['proveedores:'.$row->id] = $companyId;
            $this->writeMap('migration_company_map', [
                'legacy_source' => 'proveedores',
                'legacy_id' => (string) $row->id,
                'new_id' => $companyId,
            ]);
            $this->ensureSupplierRelationship($orId, (int) $row->id, $companyId, $row);
        }
        $this->finishProgress($bar);
        $this->counts['suppliers'] = $created;
    }

    private function ensureSupplierRelationship(int $orId, int $legacyId, int $relatedId, object $row): void
    {
        if (isset($this->relationshipMap['proveedores:'.$legacyId])) {
            return;
        }
        if ($relatedId === $orId || $relatedId < 1) {
            return;
        }
        if (! $this->execute) {
            $this->relationshipMap['proveedores:'.$legacyId] = $this->syntheticId++;

            return;
        }
        $existing = $this->dest->table('company_relationships')
            ->where('owner_company_id', $orId)
            ->where('related_company_id', $relatedId)
            ->where('kind', 'supplier')
            ->value('id');
        if ($existing) {
            $newId = (int) $existing;
        } else {
            $newId = $this->dest->table('company_relationships')->insertGetId($this->filterDestColumns('company_relationships', [
                'owner_company_id' => $orId,
                'related_company_id' => $relatedId,
                'kind' => 'supplier',
                'status' => $this->bool($row->activo ?? true) ? 'active' : 'inactive',
                'classification' => 'commercial',
                'delegation_id' => $this->catalog((int) ($row->delegacion_id ?? 0), 'delegations'),
                'tax_rate' => $row->impuesto ?? null,
                'notes' => $this->text($row->observaciones ?? null),
                'internal_notes' => $this->text($row->observaciones_privadas ?? null),
                'notes_alert' => $this->bool($row->observaciones_aviso ?? false),
                'internal_notes_alert' => $this->bool($row->observaciones_privadas_aviso ?? false),
                'is_reviewed' => $this->bool($row->revisado ?? false),
                'deleted_token' => '',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]));
        }
        $this->relationshipMap['proveedores:'.$legacyId] = $newId;
        $this->writeMap('migration_company_relationship_map', [
            'legacy_source' => 'proveedores',
            'legacy_id' => $legacyId,
            'new_id' => $newId,
            'kind' => 'supplier',
        ]);
        $this->bump('supplier_relationships');
    }

    private function importBrandCollaborators(): void
    {
        if (! $this->sourceHasTable('colaboradores') || ! $this->destHasTable('brand_collaborators')) {
            return;
        }
        $query = $this->legacy->table('colaboradores')->where('modelo_id', 5);
        if ($this->sourceHasColumn('colaboradores', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $rows = $query->orderBy('id')->get();
        $bar = $this->startProgress('Brand collaborators', $rows->count());
        $inserted = 0;
        foreach ($rows as $row) {
            $bar?->advance();
            $brandId = $this->brandMap[(int) ($row->relacion_id ?? $row->brand_id ?? 0)] ?? null;
            $userId = $this->mappedUser($row->user_id);
            if ($brandId === null || $userId === null || $brandId < 1 || $userId < 1) {
                continue;
            }
            if ($this->mappedId('brand_collaborators', $row->id) !== null) {
                continue;
            }
            if ($this->execute) {
                $this->dest->table('brand_collaborators')->insertOrIgnore([
                    'brand_id' => $brandId,
                    'user_id' => $userId,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
            $this->rememberMapped('brand_collaborators', $row->id, (int) $row->id);
            $inserted++;
        }
        $this->finishProgress($bar);
        $this->counts['brand_collaborators'] = $inserted;
    }

    private function importBrandMessages(): void
    {
        if (! $this->sourceHasTable('mensajes_marca') || ! $this->destHasTable('brand_messages')) {
            return;
        }
        $query = $this->legacy->table('mensajes_marca')->whereNull('deleted_at');
        $this->migrateChunked('Brand messages', $query, 'brand_messages', 'brand_messages', function (object $row): ?array {
            $brandId = $this->brandMap[(int) $row->marca_id] ?? null;
            $userId = $this->mappedUser($row->usuario_id);
            if ($brandId === null || $userId === null || $brandId < 1 || $userId < 1) {
                return null;
            }
            $tipo = strtolower((string) ($row->tipo ?? 'text'));
            $type = in_array($tipo, ['image', 'pdf', 'word', 'file'], true) ? $tipo : 'text';
            $body = $this->text($row->mensaje);
            $attachmentPath = $type === 'text' ? null : $body;
            $attachmentName = $type === 'text' ? null : basename((string) ($row->mensaje ?? ''));

            return [
                'brand_id' => $brandId,
                'user_id' => $userId,
                'body' => $type === 'text' ? $body : null,
                'type' => $type,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName === '' ? null : $attachmentName,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importVehicles(): void
    {
        if (! $this->sourceHasTable('vehiculos') || ! $this->destHasTable('vehicles')) {
            return;
        }
        $query = $this->legacy->table('vehiculos');
        if ($this->sourceHasColumn('vehiculos', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Vehicles', $query, 'vehicles', 'vehicles', function (object $row): ?array {
            $relId = $this->relationshipMap['tecnicos:'.$row->tecnico_id] ?? null;

            return [
                'brand' => $this->str($row->marca, 255),
                'model' => $this->str($row->modelo, 255),
                'license_plate' => $this->str($row->matricula, 255),
                'company_relationship_id' => ($relId !== null && $relId > 0) ? $relId : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importArticles(): void
    {
        if (! $this->sourceHasTable('articulos') || ! $this->destHasTable('articles')) {
            return;
        }
        $query = $this->legacy->table('articulos');
        if ($this->sourceHasColumn('articulos', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $existingCodes = [];
        foreach ($this->dest->table('articles')->get(['id', 'code']) as $article) {
            $existingCodes[strtolower((string) $article->code)] = (int) $article->id;
        }
        $this->migrateChunked('Articles', $query, 'articles', 'articles', function (object $row) use (&$existingCodes): ?array {
            $code = $this->clip($row->codigo, 50, 'ART'.$row->id);
            $key = strtolower((string) $code);
            if (isset($existingCodes[$key])) {
                if ($existingCodes[$key] > 0) {
                    $this->rememberMapped('articles', $row->id, $existingCodes[$key]);
                }

                return null;
            }
            $existingCodes[$key] = 0;

            return [
                'code' => $code,
                'is_deletable' => $this->bool($row->borrable ?? true),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importContracts(): void
    {
        if (! $this->sourceHasTable('contratos') || ! $this->destHasTable('contracts')) {
            return;
        }
        $query = $this->legacy->table('contratos');
        if ($this->sourceHasColumn('contratos', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Contracts', $query, 'contracts', 'contracts', function (object $row): ?array {
            $statusId = (int) ($row->estado_id ?? 0);

            return [
                'code' => $this->clip($row->codigo, 64),
                'company_id' => $this->companyMap['clientes:'.($row->cliente_id ?? $row->customer_company_id ?? 0)] ?? null,
                'responsible_user_id' => $this->mappedUser($row->responsable_id),
                'contract_status_id' => $this->catalogExists('contract_statuses', $statusId) ? $statusId : null,
                'language_id' => $this->catalog((int) ($row->idioma_id ?? 0), 'languages'),
                'description' => $this->str($row->descripcion, 255),
                'work_order_subject' => $this->str($row->asunto_ot, 255),
                'progress' => $this->unsignedInt($row->progreso ?? null),
                'total_progress' => $this->unsignedInt($row->total_progreso ?? null),
                'total_amount' => $row->importe_total,
                'signed_at' => $row->fecha_firma,
                'canceled_at' => $row->canceled_date,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });

        if (! $this->sourceHasTable('contratos_establecimientos') || ! $this->destHasTable('contract_establishment')) {
            return;
        }
        $pivots = $this->legacy->table('contratos_establecimientos')->orderBy('id')->get();
        $bar = $this->startProgress('Contract establishments', $pivots->count());
        $inserted = 0;
        foreach ($pivots as $row) {
            $bar?->advance();
            $contractId = $this->mappedId('contracts', $row->contrato_id ?? $row->contract_id ?? 0);
            $estId = $this->establishmentMap[(int) ($row->establecimiento_id ?? 0)] ?? null;
            if ($contractId === null || $estId === null || $estId < 1) {
                continue;
            }
            if ($this->execute) {
                $this->dest->table('contract_establishment')->insertOrIgnore([
                    'contract_id' => $contractId,
                    'establishment_id' => $estId,
                ]);
            }
            $inserted++;
        }
        $this->finishProgress($bar);
        $this->counts['contract_establishment'] = $inserted;
    }

    private function importEvaluations(): void
    {
        if (! $this->sourceHasTable('evaluaciones') || ! $this->destHasTable('evaluations')) {
            return;
        }
        $query = $this->legacy->table('evaluaciones');
        if ($this->sourceHasColumn('evaluaciones', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Evaluations', $query, 'evaluations', 'evaluations', function (object $row): ?array {
            $estId = $this->establishmentMap[(int) ($row->establecimiento_id ?? 0)] ?? null;
            if ($estId === null || $estId < 1) {
                return null;
            }
            $statusId = (int) ($row->estado_id ?? 0);
            return [
                'subject' => $this->clip($row->asunto, 255),
                'public_id' => $this->clip($row->id_publico ?? null, 36) ?: (string) Str::uuid(),
                'establishment_id' => $estId,
                'evaluation_status_id' => $this->catalogExists('evaluation_statuses', $statusId) ? $statusId : null,
                'responsible_user_id' => $this->mappedUser($row->responsable_id),
                'next_action_at' => $row->fecha_proxima_accion,
                'closed_at' => $row->fecha_cierre,
                'facility_question' => $this->clip($row->pregunta_facility, 255),
                'technician_question' => $this->clip($row->pregunta_tecnico, 255),
                'visit_count' => $this->unsignedInt($row->visitas ?? null) ?? 0,
                'qc_duration_minutes' => is_numeric($row->tiempo_qc ?? null) ? (int) $row->tiempo_qc : null,
                'call_count' => $this->unsignedInt($row->num_llamadas ?? null) ?? 0,
                'first_contact_attempt_at' => $row->primer_intento_contacto,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importIncidents(int $orId): void
    {
        if (! $this->sourceHasTable('incidencias') || ! $this->destHasTable('incidents')) {
            return;
        }
        $query = $this->legacy->table('incidencias');
        if ($this->sourceHasColumn('incidencias', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Incidents', $query, 'incidents', 'incidents', function (object $row) use ($orId): ?array {
            $statusId = (int) ($row->estado_id ?? 0);
            $priorityId = (int) ($row->prioridad_id ?? 0);
            $typeId = (int) ($row->tipo_id ?? 0);
            $subtypeId = (int) ($row->subtipo_id ?? 0);
            $responsible = $this->mappedUser($row->responsable_id);
            if ($responsible === null || $responsible < 1) {
                return null;
            }
            if (! $this->catalogExists('incident_statuses', $statusId)
                || ! $this->catalogExists('incident_priorities', $priorityId)
                || ! $this->catalogExists('incident_types', $typeId)
                || ! $this->catalogExists('incident_subtypes', $subtypeId)) {
                return null;
            }
            $originModelo = (int) ($row->origen_modelo_id ?? 0);
            $originLegacy = (int) ($row->origen_relacion_id ?? 0);
            $originType = match ($originModelo) {
                7 => 'establishment',
                6 => 'company',
                5 => 'brand',
                default => null,
            };
            $originId = null;
            $establishmentId = null;
            if ($originType === 'establishment') {
                $originId = $this->establishmentMap[$originLegacy] ?? null;
                $establishmentId = $originId;
            } elseif ($originType === 'company') {
                $originId = $this->companyMap['clientes:'.$originLegacy] ?? null;
            } elseif ($originType === 'brand') {
                $originId = $this->brandMap[$originLegacy] ?? null;
            }
            $relatedModelo = (int) ($row->relacion_modelo_id ?? 0);
            $relatedLegacy = (int) ($row->relacion_relacion_id ?? 0);
            $relatedType = $relatedModelo === 21 ? 'evaluation' : null;
            $relatedId = $relatedType === 'evaluation' ? $this->mappedId('evaluations', $relatedLegacy) : null;
            $evaluationId = $relatedId;
            if ($establishmentId === null && $evaluationId !== null) {
                $establishmentId = (int) ($this->dest->table('evaluations')->where('id', $evaluationId)->value('establishment_id') ?? 0) ?: null;
            }

            return [
                'company_id' => $orId,
                'subject' => $this->clip($row->asunto, 255, 'Incident '.$row->id),
                'comment' => $this->text($row->comentario),
                'incident_status_id' => $statusId,
                'incident_priority_id' => $priorityId,
                'incident_type_id' => $typeId,
                'incident_subtype_id' => $subtypeId,
                'establishment_id' => ($establishmentId !== null && $establishmentId > 0) ? $establishmentId : null,
                'evaluation_id' => ($evaluationId !== null && $evaluationId > 0) ? $evaluationId : null,
                'control_at' => $row->fecha_control,
                'closed_at' => $row->fecha_cierre,
                'duration_seconds' => is_numeric($row->tiempo ?? null) ? (int) $row->tiempo : null,
                'qc_duration_seconds' => is_numeric($row->tiempo_qc ?? null) ? (int) $row->tiempo_qc : null,
                'requester_user_id' => $this->mappedUser($row->solicitante_id),
                'responsible_user_id' => $responsible,
                'qc_responsible_user_id' => $this->mappedUser($row->responsable_qc_id),
                'origin_type' => $originId ? $originType : null,
                'origin_id' => ($originId !== null && $originId > 0) ? $originId : null,
                'related_type' => $relatedId ? $relatedType : null,
                'related_id' => ($relatedId !== null && $relatedId > 0) ? $relatedId : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importTechnicianIncidents(): void
    {
        if (! $this->sourceHasTable('tecnicos_incidencias') || ! $this->destHasTable('technician_incidents')) {
            return;
        }
        $query = $this->legacy->table('tecnicos_incidencias');
        if ($this->sourceHasColumn('tecnicos_incidencias', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Technician incidents', $query, 'technician_incidents', 'technician_incidents', function (object $row): ?array {
            $typeId = (int) ($row->tecnico_incidencia_tipo_id ?? $row->tipo_id ?? 0);
            if (! $this->catalogExists('technician_incident_types', $typeId)) {
                return null;
            }
            $statusId = (int) ($row->estado_id ?? 0);
            $techRel = $this->relationshipMap['tecnicos:'.$row->tecnico_id] ?? null;

            return [
                'status_id' => $this->catalogExists('technician_incident_statuses', $statusId) ? $statusId : null,
                'technician_incident_type_id' => $typeId,
                'incident_text' => $this->text($row->texto_incidencia ?? null),
                'response_text' => $this->text($row->texto_respuesta ?? null),
                'requested_by_id' => $this->mappedUser($row->solicitado_por_id),
                'responded_by_id' => $this->mappedUser($row->respondido_por_id),
                'responded_at' => $row->fecha_respuesta,
                'technician_id' => ($techRel !== null && $techRel > 0) ? $techRel : null,
                'is_verified' => $this->bool($row->verificado ?? false),
                'verified_at' => $row->fecha_verificado,
                'verified_by_id' => $this->mappedUser($row->verificado_por_id),
                'due_at' => $row->fecha_limite,
                'negotiation_succeeded' => isset($row->negociacion_exitosa) ? ($row->negociacion_exitosa === null ? null : $this->bool($row->negociacion_exitosa)) : null,
                'unsuccessful_negotiation_solution' => $this->text($row->solucion_negociacion_no_exitosa ?? null),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importTechnicianIncidentMessages(): void
    {
        if (! $this->sourceHasTable('tecnicos_incidencias_mensajes') || ! $this->destHasTable('technician_incident_messages')) {
            return;
        }
        $query = $this->legacy->table('tecnicos_incidencias_mensajes');
        $this->migrateChunked('Technician incident messages', $query, 'technician_incident_messages', 'technician_incident_messages', function (object $row): ?array {
            $parent = $this->mappedId('technician_incidents', $row->tecnico_incidencia_id);
            $userId = $this->mappedUser($row->usuario_id);
            if ($parent === null || $userId === null || $parent < 1 || $userId < 1) {
                return null;
            }
            $tipo = strtolower((string) ($row->tipo ?? 'text'));
            $body = $this->text($row->mensaje) ?? '';
            if ($body === '' && ! in_array($tipo, ['image', 'file', 'pdf'], true)) {
                return null;
            }
            if ($body === '') {
                $body = (string) ($row->mensaje ?? '-');
            }

            return [
                'technician_incident_id' => $parent,
                'user_id' => $userId,
                'body' => $body,
                'type' => $tipo !== '' ? $tipo : 'text',
                'attachment_path' => in_array($tipo, ['image', 'file', 'pdf'], true) ? $body : null,
                'attachment_name' => in_array($tipo, ['image', 'file', 'pdf'], true) ? basename($body) : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        });
    }

    private function importArticleClients(): void
    {
        if (! $this->sourceHasTable('articulo_cliente') || ! $this->destHasTable('article_clients')) {
            return;
        }
        $query = $this->legacy->table('articulo_cliente')->orderBy('articulo_id')->orderBy('cliente_id');
        $this->migrateKeyed(
            'Article client prices',
            $query,
            'article_clients',
            'article_clients',
            fn (object $row): string => ($row->articulo_id ?? 0).':'.($row->cliente_id ?? 0),
            function (object $row): ?array {
                $articleId = $this->mappedId('articles', $row->articulo_id ?? $row->article_id ?? 0);
                $relId = $this->relationshipMap['clientes:'.($row->cliente_id ?? 0)] ?? null;
                if ($articleId === null || $relId === null || $articleId < 1 || $relId < 1) {
                    return null;
                }
                $price = $row->precio_venta ?? $row->sale_price ?? null;
                if (! is_numeric($price)) {
                    return null;
                }

                return [
                    'article_id' => $articleId,
                    'company_relationship_id' => $relId,
                    'sale_price' => $price,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ];
            },
        );
    }

    private function importCollaborators(): void
    {
        if (! $this->sourceHasTable('colaboradores')) {
            return;
        }
        $routes = [
            6 => ['company_relationship_collaborators', 'company_relationship_id', 'clientes'],
            3 => ['company_relationship_collaborators', 'company_relationship_id', 'tecnicos'],
            4 => ['company_relationship_collaborators', 'company_relationship_id', 'proveedores'],
            7 => ['establishment_collaborators', 'establishment_id', 'establishments'],
            22 => ['incident_collaborators', 'incident_id', 'incidents'],
            1 => ['work_order_collaborators', 'work_order_id', 'presupuestos'],
            2 => ['work_order_collaborators', 'work_order_id', 'ots'],
        ];
        $query = $this->legacy->table('colaboradores')->whereIn('modelo_id', array_keys($routes));
        if ($this->sourceHasColumn('colaboradores', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $rows = $query->orderBy('id')->get();
        $bar = $this->startProgress('Collaborators', $rows->count());
        $inserted = 0;
        foreach ($rows as $row) {
            $bar?->advance();
            $route = $routes[(int) $row->modelo_id] ?? null;
            if ($route === null) {
                continue;
            }
            [$table, $fk, $map] = $route;
            if (! $this->destHasTable($table)) {
                continue;
            }
            $userId = $this->mappedUser($row->user_id);
            if ($userId === null || $userId < 1) {
                continue;
            }
            $parentId = match ($map) {
                'clientes' => $this->relationshipMap['clientes:'.$row->relacion_id] ?? null,
                'tecnicos' => $this->relationshipMap['tecnicos:'.$row->relacion_id] ?? null,
                'proveedores' => $this->relationshipMap['proveedores:'.$row->relacion_id] ?? null,
                'establishments' => $this->establishmentMap[(int) $row->relacion_id] ?? null,
                'incidents' => $this->mappedId('incidents', $row->relacion_id),
                'presupuestos' => $this->mappedId('presupuestos', $row->relacion_id),
                'ots' => $this->mappedId('ots', $row->relacion_id),
                default => null,
            };
            if ($parentId === null || $parentId < 1) {
                continue;
            }
            if ($this->execute) {
                $this->dest->table($table)->insertOrIgnore([
                    $fk => $parentId,
                    'user_id' => $userId,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
            $inserted++;
        }
        $this->finishProgress($bar);
        $this->counts['collaborators'] = $inserted;
    }

    private function importRequesters(): void
    {
        if (! $this->sourceHasTable('solicitantes') || ! $this->destHasTable('requesters')) {
            return;
        }
        $query = $this->legacy->table('solicitantes');
        if ($this->sourceHasColumn('solicitantes', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Requesters', $query, 'requesters', 'requesters', function (object $row): ?array {
            $companyId = $this->companyMap['clientes:'.($row->cliente_id ?? 0)] ?? null;
            if ($companyId === null || $companyId < 1) {
                return null;
            }

            return [
                'name' => $this->clip($row->nombre_completo ?? $row->nombre ?? $row->name ?? null, 255, 'Requester '.$row->id),
                'emails' => $this->emailsJson($row->correos ?? $row->emails ?? $row->email ?? $row->correo ?? null),
                'company_id' => $companyId,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importWorkOrdersFromEstimates(int $orId): void
    {
        if (! $this->sourceHasTable('presupuestos') || ! $this->destHasTable('work_orders')) {
            return;
        }
        $query = $this->legacy->table('presupuestos');
        if ($this->sourceHasColumn('presupuestos', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Work orders (estimates)', $query, 'presupuestos', 'work_orders', function (object $row) use ($orId): ?array {
            return $this->workOrderPayload($row, $orId, true);
        });
    }

    private function importWorkOrdersFromOts(int $orId): void
    {
        if (! $this->sourceHasTable('ots') || ! $this->destHasTable('work_orders')) {
            return;
        }
        $query = $this->legacy->table('ots');
        if ($this->sourceHasColumn('ots', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Work orders (OTs)', $query, 'ots', 'work_orders', function (object $row) use ($orId): ?array {
            return $this->workOrderPayload($row, $orId, false);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function workOrderPayload(object $row, int $orId, bool $estimate): ?array
    {
        $estId = $this->establishmentMap[(int) ($row->establecimiento_id ?? 0)] ?? null;
        if ($estId === null || $estId < 1) {
            return null;
        }
        $statusId = (int) ($row->estado_id ?? 0);
        if (! $this->catalogExists('work_order_statuses', $statusId)) {
            return null;
        }
        $typeId = (int) ($row->ot_tipo_id ?? 0);
        $parentOt = $estimate ? null : $this->mappedId('ots', $row->ot_padre_id ?? 0);
        $sourceEstimate = $estimate ? null : $this->mappedId('presupuestos', $row->presupuesto_id ?? 0);
        $sourceOt = $estimate ? $this->mappedId('ots', $row->ot_id ?? 0) : null;

        return [
            'code' => $this->clip($row->codigo, 64),
            'is_estimate' => $estimate ? 1 : 0,
            'is_work_order' => $estimate ? 0 : 1,
            'estimate_num' => $estimate ? $this->clip($row->codigo, 64) : null,
            'work_order_num' => $estimate ? null : $this->clip($row->codigo, 64),
            'subject' => $this->clip($row->asunto, 255, $estimate ? 'Estimate '.$row->id : 'OT '.$row->id),
            'reference' => $this->clip($row->referencia ?? null, 255),
            'purchase_order' => $this->clip($row->po ?? null, 255),
            'stage' => $estimate ? 'estimate' : 'work_order',
            'status_id' => $statusId,
            'work_order_type_id' => $this->catalogExists('work_order_types', $typeId) ? $typeId : null,
            'client_priority_id' => $this->catalog((int) ($row->prioridad_id ?? 0), 'client_priorities'),
            'is_urgent' => $this->bool($row->urgencia ?? false),
            'establishment_id' => $estId,
            'owner_company_id' => $orId,
            'billing_company_id' => $this->companyMap['clientes:'.($row->cliente_facturacion_id ?? $row->billing_customer_company_id ?? 0)] ?? null,
            'responsible_user_id' => $this->mappedUser($row->responsable_id),
            'requester_id' => $this->mappedId('requesters', $row->solicitante_id ?? 0),
            'delegation_id' => $this->catalog((int) ($row->delegacion_id ?? 0), 'delegations'),
            'currency_id' => $this->catalog((int) ($row->moneda_id ?? 0), 'currencies'),
            'contract_id' => $this->mappedId('contracts', $row->contrato_id ?? 0),
            'incident_id' => $this->mappedId('incidents', $row->incidencia_id ?? 0),
            'evaluation_id' => $this->mappedId('evaluations', $row->evaluacion_id ?? 0),
            'parent_work_order_id' => ($parentOt !== null && $parentOt > 0) ? $parentOt : null,
            'source_work_order_id' => ($sourceEstimate !== null && $sourceEstimate > 0)
                ? $sourceEstimate
                : (($sourceOt !== null && $sourceOt > 0) ? $sourceOt : null),
            'is_intercompany' => $this->bool($row->is_intercompany ?? false),
            'sync_grouping' => $this->bool($row->sincronizar_agrupacion ?? false),
            'notes' => $this->text($row->observaciones ?? null),
            'internal_notes' => $this->text($row->observaciones_privadas ?? null),
            'notes_alert' => $this->bool($row->observaciones_aviso ?? false),
            'internal_notes_alert' => $this->bool($row->observaciones_privadas_aviso ?? false),
            'is_reviewed' => $this->bool($row->revisado ?? false),
            'received_at' => $row->fecha_recibida ?? null,
            'intervention_at' => $row->fecha_intervencion ?? null,
            'assigned_at' => $row->fecha_asignacion_intervencion ?? null,
            'due_at' => $row->fecha_vencimiento_previsto ?? $row->fecha_cierre_esperado ?? null,
            'expected_close_at' => $row->fecha_cierre_esperado ?? null,
            'sla_at' => $row->fecha_sla ?? null,
            'closed_at' => $row->fecha_cierre ?? null,
            'billed_at' => $row->fecha_facturacion ?? null,
            'sent_at' => $row->fecha_envio ?? null,
            'received_at_overridden' => $this->bool($row->fecha_recibida_sobreescrita ?? false),
            'sla_justification' => $this->text($row->justificacion_sla ?? null),
            'is_sla_reviewed' => $this->bool($row->sla_revisado ?? false),
            'tax_rate' => $row->iva_valor ?? null,
            'tax_included' => $this->bool($row->iva_incluido ?? false),
            'net_amount' => $row->base_euros ?? $row->precio_venta_euro ?? $row->base_moneda ?? null,
            'tax_amount' => $row->iva_euros ?? $row->iva_moneda ?? null,
            'total_amount' => $row->total_euros ?? $row->total_moneda ?? $row->precio_venta ?? null,
            'total_euros' => $row->total_euros ?? null,
            'cost_amount' => $row->coste ?? $row->precio_compra_euro ?? $row->precio_compra ?? null,
            'margin_amount' => $row->margen ?? null,
            'profit_amount' => $row->beneficio ?? null,
            'requires_billing_lines' => $this->bool($row->requiere_lineas_facturacion ?? false),
            'notify_technician' => $this->bool($row->notificar_tecnico ?? false),
            'organization_seconds' => $this->unsignedInt($row->tiempo_organizacion ?? null),
            'completion_seconds' => $this->unsignedInt($row->tiempo_finalizacion ?? null),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'deleted_at' => $row->deleted_at ?? null,
        ];
    }

    private function importTechnicianRequests(int $orId): void
    {
        if (! $this->sourceHasTable('peticiones') || ! $this->destHasTable('technician_requests')) {
            return;
        }
        $query = $this->legacy->table('peticiones');
        if ($this->sourceHasColumn('peticiones', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Technician requests', $query, 'technician_requests', 'technician_requests', function (object $row) use ($orId): ?array {
            $statusId = (int) ($row->estado_id ?? 0);
            $priorityId = (int) ($row->prioridad_id ?? 0);
            $parentId = $this->mappedId('technician_requests', $row->peticion_id ?? 0);

            return [
                'company_id' => $orId,
                'is_screening' => $this->bool($row->filtraje ?? $row->is_screening ?? false),
                'parent_technician_request_id' => ($parentId !== null && $parentId > 0) ? $parentId : null,
                'work_order_id' => $this->mappedId('ots', $row->ot_id ?? 0),
                'code' => $this->clip($row->codigo ?? $row->code ?? null, 255),
                'description' => $this->text($row->descripcion ?? $row->description ?? null),
                'notes' => $this->text($row->observaciones ?? $row->notes ?? null),
                'internal_notes' => $this->text($row->observaciones_privada ?? $row->observaciones_privadas ?? null),
                'city' => $this->clip($row->poblacion ?? $row->city ?? null, 255),
                'postal_code' => $this->clip($row->codigo_postal ?? null, 32),
                'address_line' => $this->clip($row->direccion_1 ?? $row->direccion ?? null, 255),
                'province_name' => $this->clip($row->provincia ?? $row->province_name ?? null, 255),
                'country_id' => $this->catalog((int) ($row->pais_id ?? 0), 'countries'),
                'language_id' => $this->catalog((int) ($row->idioma_id ?? 0), 'languages'),
                'requester_user_id' => $this->mappedUser($row->solicitado_por_id ?? $row->solicitante_id ?? $row->requester_user_id ?? null),
                'responsible_user_id' => $this->mappedUser($row->responsable_tec_id ?? $row->responsable_id ?? null),
                'technician_request_priority_id' => $this->catalogExists('technician_request_priorities', $priorityId) ? $priorityId : null,
                'technician_request_status_id' => $this->catalogExists('technician_request_statuses', $statusId) ? $statusId : null,
                'resolved_at' => $row->fecha_resolucion ?? $row->resolved_at ?? null,
                'due_at' => $row->fecha_limite_resolucion ?? $row->fecha_limite ?? $row->due_at ?? null,
                'next_action_at' => $row->fecha_proxima_accion ?? null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importCompliments(): void
    {
        if (! $this->sourceHasTable('felicitaciones') || ! $this->destHasTable('compliments')) {
            return;
        }
        $query = $this->legacy->table('felicitaciones');
        if ($this->sourceHasColumn('felicitaciones', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Compliments', $query, 'compliments', 'compliments', function (object $row): ?array {
            $typeId = (int) ($row->tipo_felicitacion_id ?? $row->tipo_id ?? $row->tipos_felicitacion_id ?? $row->compliment_type_id ?? 0);
            if (! $this->catalogExists('compliment_types', $typeId)) {
                $typeId = (int) ($this->dest->table('compliment_types')->min('id') ?? 0);
            }
            if ($typeId < 1) {
                return null;
            }
            $modelo = (int) ($row->modelo_id ?? 0);
            $relacion = (int) ($row->relacion_id ?? 0);
            $brandId = $modelo === 5 ? ($this->brandMap[$relacion] ?? null) : null;
            $relId = $modelo === 6 ? ($this->relationshipMap['clientes:'.$relacion] ?? null) : null;
            $estId = $modelo === 7 ? ($this->establishmentMap[$relacion] ?? null) : null;
            $subjectType = match (true) {
                $brandId !== null && $brandId > 0 => 'brand',
                $relId !== null && $relId > 0 => 'customer',
                $estId !== null && $estId > 0 => 'establishment',
                default => null,
            };
            if ($subjectType === null) {
                return null;
            }

            return [
                'subject_type' => $subjectType,
                'brand_id' => $subjectType === 'brand' ? $brandId : null,
                'company_relationship_id' => $subjectType === 'customer' ? $relId : null,
                'establishment_id' => $subjectType === 'establishment' ? $estId : null,
                'compliment_type_id' => $typeId,
                'comment' => $this->text($row->comentario ?? $row->comment ?? $row->mensaje ?? null),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importArticleLanguages(): void
    {
        if (! $this->sourceHasTable('articulo_idiomas') || ! $this->destHasTable('article_languages')) {
            return;
        }
        $this->migrateKeyed(
            'Article languages',
            $this->legacy->table('articulo_idiomas')->orderBy('articulo_id')->orderBy('idioma_id'),
            'article_languages',
            'article_languages',
            fn (object $row): string => ($row->articulo_id ?? 0).':'.($row->idioma_id ?? 0),
            function (object $row): ?array {
                $articleId = $this->mappedId('articles', $row->articulo_id ?? 0);
                $languageId = $this->catalog((int) ($row->idioma_id ?? 0), 'languages');
                $name = $this->clip($row->nombre, 255);
                if ($articleId === null || $languageId === null || $articleId < 1 || $name === null) {
                    return null;
                }

                return [
                    'article_id' => $articleId,
                    'language_id' => $languageId,
                    'name' => $name,
                    'description' => $this->text($row->descripcion ?? null),
                ];
            },
        );
    }

    private function importIncidentLines(): void
    {
        $source = $this->sourceFirstTable('lineas_incidencia', 'incidencias_lineas');
        if ($source === null || ! $this->destHasTable('incident_lines')) {
            return;
        }
        $query = $this->legacy->table($source);
        if ($this->sourceHasColumn($source, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $this->migrateChunked('Incident lines', $query, 'incident_lines', 'incident_lines', function (object $row): ?array {
            $incidentId = $this->mappedId('incidents', $row->incidencia_id ?? $row->incident_id ?? 0);
            $userId = $this->mappedUser($row->usuario_id ?? $row->user_id ?? null);
            $statusId = (int) ($row->estado_id ?? $row->incident_status_id ?? 0);
            if ($incidentId === null || $userId === null || $incidentId < 1 || $userId < 1) {
                return null;
            }
            if (! $this->catalogExists('incident_statuses', $statusId)) {
                return null;
            }

            return [
                'incident_id' => $incidentId,
                'started_at' => $row->fecha_inicio ?? $row->started_at ?? null,
                'ended_at' => $row->fecha_final ?? $row->ended_at ?? null,
                'comment' => $this->text($row->comentario ?? $row->comment ?? null) ?? '',
                'incident_status_id' => $statusId,
                'user_id' => $userId,
                'duration_minutes' => $this->unsignedInt($row->tiempo ?? $row->duration_minutes ?? null) ?? 0,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
                'deleted_at' => $row->deleted_at ?? null,
            ];
        });
    }

    private function importWorkOrderLines(): void
    {
        if (! $this->destHasTable('work_order_lines')) {
            return;
        }
        foreach ([
            ['presupuestos_lineas_facturacion', 'presupuesto_id', 'presupuestos', 'Work order lines (estimates)'],
            ['ots_lineas_facturacion', 'ot_id', 'ots', 'Work order lines (OTs)'],
        ] as [$source, $fk, $entity, $label]) {
            if (! $this->sourceHasTable($source)) {
                continue;
            }
            $query = $this->legacy->table($source);
            if ($this->sourceHasColumn($source, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $this->migrateChunked($label, $query, $source, 'work_order_lines', function (object $row) use ($fk, $entity): ?array {
                $workOrderId = $this->mappedId($entity, $row->{$fk} ?? 0);
                if ($workOrderId === null || $workOrderId < 1) {
                    return null;
                }
                $articleId = $this->mappedId('articles', $row->articulo_id ?? 0);

                return [
                    'work_order_id' => $workOrderId,
                    'article_id' => ($articleId !== null && $articleId > 0) ? $articleId : null,
                    'description' => $this->clip($row->descripcion, 255),
                    'quantity' => is_numeric($row->cantidad ?? null) ? $row->cantidad : 1,
                    'unit_price' => is_numeric($row->precio_unitario ?? null) ? $row->precio_unitario : 0,
                    'unit_price_euros' => is_numeric($row->precio_unitario_euros ?? null) ? $row->precio_unitario_euros : null,
                    'net_amount' => is_numeric($row->base_imponible ?? null) ? $row->base_imponible : 0,
                    'sort_order' => $this->unsignedInt($row->orden ?? null) ?? 0,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                    'deleted_at' => $row->deleted_at ?? null,
                ];
            });
        }
    }

    private function importWorkOrderTechnicians(): void
    {
        if (! $this->destHasTable('work_order_technicians')) {
            return;
        }
        if ($this->sourceHasTable('presupuestos_solicitados')) {
            $query = $this->legacy->table('presupuestos_solicitados');
            if ($this->sourceHasColumn('presupuestos_solicitados', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $this->migrateChunked('Work order technicians (estimates)', $query, 'presupuestos_solicitados', 'work_order_technicians', function (object $row): ?array {
                $workOrderId = $this->mappedId('presupuestos', $row->presupuesto_id ?? 0);
                $relId = $this->relationshipMap['tecnicos:'.($row->tecnico_id ?? 0)] ?? null;
                if ($workOrderId === null || $relId === null || $workOrderId < 1 || $relId < 1) {
                    return null;
                }

                return [
                    'work_order_id' => $workOrderId,
                    'company_relationship_id' => $relId,
                    'is_selected' => $this->bool($row->seleccionado ?? false),
                    'quote_net_amount' => $row->base_moneda ?? $row->precio ?? null,
                    'quoted_at' => $row->fecha_moneda ?? null,
                    'quote_tax_amount' => $row->iva_moneda ?? $row->iva_euros ?? null,
                    'quote_total_amount' => $row->total_moneda ?? null,
                    'quote_total_euros' => $row->total_euros ?? null,
                    'tax_rate' => $row->iva_valor ?? null,
                    'tax_included' => $this->bool($row->iva_incluido ?? false),
                    'currency_id' => $this->catalog((int) ($row->moneda_id ?? 0), 'currencies'),
                    'delegation_id' => $this->catalog((int) ($row->delegacion_id ?? 0), 'delegations'),
                    'quote_document_path' => $this->clip($row->pdf ?? null, 255),
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                    'deleted_at' => $row->deleted_at ?? null,
                ];
            });
        }
        if ($this->sourceHasTable('ot_tecnico')) {
            $query = $this->legacy->table('ot_tecnico');
            if ($this->sourceHasColumn('ot_tecnico', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $this->migrateChunked('Work order technicians (OTs)', $query, 'ot_tecnico', 'work_order_technicians', function (object $row): ?array {
                $workOrderId = $this->mappedId('ots', $row->ot_id ?? 0);
                $relId = $this->relationshipMap['tecnicos:'.($row->tecnico_id ?? 0)] ?? null;
                if ($workOrderId === null || $relId === null || $workOrderId < 1 || $relId < 1) {
                    return null;
                }
                $statusId = (int) ($row->estado_id ?? 0);
                $confirmId = (int) ($row->tipo_confirmacion_asistencia_id ?? 0);
                $uuid = $this->clip($row->uuid ?? null, 36);

                return [
                    'work_order_id' => $workOrderId,
                    'company_relationship_id' => $relId,
                    'is_selected' => $this->bool($row->seleccionado ?? false),
                    'description' => $this->text($row->descripcion ?? null),
                    'status_id' => $this->catalogExists('work_order_technician_statuses', $statusId) ? $statusId : null,
                    'attendance_confirmation_type_id' => $this->catalogExists('technician_attendance_confirmation_types', $confirmId) ? $confirmId : null,
                    'public_id' => $uuid,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                    'deleted_at' => $row->deleted_at ?? null,
                ];
            });
        }
    }

    private function importTechnicianRequestTechnicians(): void
    {
        if (! $this->sourceHasTable('peticion_tecnico') || ! $this->destHasTable('technician_request_technician')) {
            return;
        }
        $this->migrateKeyed(
            'Technician request technicians',
            $this->legacy->table('peticion_tecnico')->orderBy('peticion_id')->orderBy('tecnico_id'),
            'technician_request_technician',
            'technician_request_technician',
            fn (object $row): string => ($row->peticion_id ?? 0).':'.($row->tecnico_id ?? 0),
            function (object $row): ?array {
                $requestId = $this->mappedId('technician_requests', $row->peticion_id ?? 0);
                $relId = $this->relationshipMap['tecnicos:'.($row->tecnico_id ?? 0)] ?? null;
                if ($requestId === null || $relId === null || $requestId < 1 || $relId < 1) {
                    return null;
                }

                return [
                    'technician_request_id' => $requestId,
                    'company_relationship_id' => $relId,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ];
            },
        );
    }

    /**
     * @param  callable(object): string  $keyFn
     * @param  callable(object): (?array<string, mixed>)  $rowMapper
     */
    private function migrateKeyed(string $label, mixed $query, string $entity, string $destTable, callable $keyFn, callable $rowMapper): void
    {
        $mapped = $this->mappedCount($entity);
        $total = (int) (clone $query)->count();
        if ($mapped > 0 && $mapped >= $total) {
            $this->skipPhase($label, $mapped);
            $this->counts[$entity] = 0;

            return;
        }

        $pending = [];
        (clone $query)->chunk($this->chunk, function ($rows) use (&$pending, $entity, $keyFn): void {
            foreach ($rows as $row) {
                $key = $keyFn($row);
                if ($key !== '' && $this->mappedId($entity, $key) === null) {
                    $pending[] = $row;
                }
            }
        });
        if ($pending === []) {
            $this->skipPhase($label, $mapped, $mapped > 0 ? 'already mapped' : 'nothing to import');
            $this->counts[$entity] = 0;

            return;
        }

        $bar = $this->startProgress($label.' (missing)', count($pending));
        $created = 0;
        foreach (array_chunk($pending, $this->chunk) as $rows) {
            $batch = [];
            foreach ($rows as $row) {
                $bar?->advance();
                $key = $keyFn($row);
                $payload = $rowMapper($row);
                if ($payload === null) {
                    continue;
                }
                $created++;
                if (! $this->execute) {
                    $this->rememberMapped($entity, $key, $this->syntheticId++);

                    continue;
                }
                $batch[] = [$key, $payload];
            }
            $this->insertMappedBatch($entity, $destTable, $batch);
        }
        $this->finishProgress($bar);
        $this->counts[$entity] = $created;
    }

    /**
     * @param  callable(object): (?array<string, mixed>)  $rowMapper
     */
    private function migrateChunked(string $label, mixed $query, string $entity, string $destTable, callable $rowMapper): void
    {
        $mapped = $this->mappedCount($entity);
        $total = (int) (clone $query)->count();
        if ($mapped > 0 && $mapped >= $total) {
            $this->skipPhase($label, $mapped);
            $this->counts[$entity] = 0;

            return;
        }

        $todoIds = $this->unmappedSourceIds($query, $entity);
        if ($todoIds === []) {
            $this->skipPhase($label, $mapped, $mapped > 0 ? 'already mapped' : 'nothing to import');
            $this->counts[$entity] = 0;

            return;
        }

        $bar = $this->startProgress($label.' (missing '.count($todoIds).')', count($todoIds));
        $created = 0;
        foreach (array_chunk($todoIds, $this->chunk) as $idChunk) {
            $rows = (clone $query)->whereIn('id', $idChunk)->orderBy('id')->get();
            $batch = [];
            foreach ($rows as $row) {
                $bar?->advance();
                if ($this->mappedId($entity, $row->id) !== null) {
                    continue;
                }
                $payload = $rowMapper($row);
                if ($payload === null) {
                    continue;
                }
                $created++;
                if (! $this->execute) {
                    $this->rememberMapped($entity, $row->id, $this->syntheticId++);

                    continue;
                }
                $batch[] = [(string) $row->id, $payload];
            }
            $this->insertMappedBatch($entity, $destTable, $batch);
        }
        $this->finishProgress($bar);
        $this->counts[$entity] = $created;
    }

    /**
     * @param  list<array{0: int|string, 1: array<string, mixed>}>  $batch
     */
    private function insertMappedBatch(string $entity, string $destTable, array $batch): void
    {
        if ($batch === [] || ! $this->execute) {
            return;
        }
        $hasId = in_array('id', $this->destColumns($destTable), true);
        $next = $hasId ? $this->nextDestId($destTable) : 0;
        $insert = [];
        $keys = [];
        foreach ($batch as [$legacyId, $payload]) {
            if ($hasId) {
                $id = $next++;
                $payload['id'] = $id;
                $keys[] = [$legacyId, $id];
            } else {
                $keys[] = [$legacyId, 1];
            }
            $insert[] = $this->filterDestColumns($destTable, $payload);
        }
        $ok = [];
        try {
            $this->dest->table($destTable)->insert($insert);
            $ok = $keys;
        } catch (\Throwable) {
            foreach ($insert as $i => $row) {
                try {
                    $this->dest->table($destTable)->insert($row);
                    $ok[] = $keys[$i];
                } catch (\Throwable $inner) {
                    $this->quarantine($entity, (string) $batch[$i][0], null, 'skipped', $inner->getMessage(), null);
                }
            }
        }
        foreach ($ok as [$legacyId, $id]) {
            $this->rememberMapped($entity, $legacyId, $id);
        }
        $this->flushIdMaps();
    }
}
