# 15 — Table inventory (legacy ↔ v2)

Quick reference in three parts:

1. **Old → new** — legacy tables that have a v2 counterpart (schema mapped / created)
2. **New only** — v2 tables with no legacy table of the same meaning
3. **Skipped** — legacy tables deliberately not brought over (ignore / defer / unresolved)

**Important:** most areas have **schema + CRUD** only. Production **rows** are generally **not** copied yet.

See also: [01-table-mapping.md](01-table-mapping.md), [09](09-work-orders.md)–[14](14-contract-iterations.md).

---

## 1. Old tables → new tables

| Old (legacy) | New (v2) |
| --- | --- |
| `empresas` | `companies` (`kind=operating_company`) |
| `clientes` | `companies` + `company_relationships` (`customer`) |
| `proveedores` | `companies` + `company_relationships` (`supplier`) |
| `tecnicos` | `companies` + `company_relationships` (`technician`) |
| `users` | `users` |
| `marcas` | `brands` |
| `establecimientos` | `establishments` |
| `delegaciones` | `delegations` |
| `paises` | `countries` |
| `provincias` | `provinces` |
| `idiomas` | `languages` |
| `zonas_horarias` | `timezones` |
| `equipos` | `teams` |
| `puestos` | `job_titles` |
| `monedas` | `currencies` |
| `bancos` | `banks` |
| `series` | `series` |
| `prioridades` | `client_priorities` |
| `centros_coste` | `cost_centers` |
| `tipos_gasto` | `expense_types` |
| `tipos_otros_gastos` | `other_expense_types` |
| `tipos_coste_indirecto` | `indirect_cost_types` |
| `tipos_servicio` | `service_types` |
| `tipos_servicio_global` | `global_service_types` |
| `tipos_establecimiento` | `establishment_types` |
| `formas_pago` | `payment_methods` |
| `documentos_pago` | `payment_documents` |
| `checklists` | `checklists` |
| `trabajos_a_realizar` | `tasks_to_perform` |
| `tarifas_cliente` | `client_rates` |
| `tecnico_tipos_confirmacion_asistencia` | `technician_attendance_confirmation_types` |
| `ots_tipos` | `work_order_types` |
| `ots_tipos` ↔ service types | `work_order_type_service_types` |
| technician ↔ service / delegation links | `technician_service_types`, `technician_global_service_types`, `technician_alternative_delegations` |
| `estados` (QC incidents) | `incident_statuses` |
| `estados` (technician incidents) | `technician_incident_statuses` |
| `estados` (contracts) | `contract_statuses` |
| `estados` (evaluations) | `evaluation_statuses` |
| `estados` (OT + presupuesto) | `work_order_statuses` |
| `estados` (167–169 attendance) | `work_order_technician_statuses` |
| `incidencias` | `incidents` |
| `incidencias_tipos` | `incident_types` |
| `incidencias_gravedades` / priorities | `incident_priorities` |
| `incidencias` subtypes / lines | `incident_subtypes`, `incident_lines` |
| status↔type exclusions | `incident_status_type_exclusions` |
| `incidencias_colaboradores` | `incident_collaborators` |
| `tecnicos_incidencias` | `technician_incidents` |
| `tecnicos_incidencias_tipos` | `technician_incident_types` |
| `tecnicos_incidencias_mensajes` | `technician_incident_messages` |
| `contratos` | `contracts` |
| `contratos_establecimientos` | `contract_establishment` |
| `archivos` (contrato) | `contract_attachments` |
| `iteraciones` | `contract_iterations` |
| `contract_invoicing_aggregations` | `contract_invoicing_aggregations` |
| `evaluaciones` | `evaluations` |
| `articulos` | `articles` |
| `articulo_cliente` | `article_clients` |
| article languages | `article_languages` |
| `vehiculos` | `vehicles` |
| `colaboradores` (establishment) | `establishment_collaborators` |
| `colaboradores` (company relationship) | `company_relationship_collaborators` |
| `colaboradores` (QC incident) | `incident_collaborators` |
| `colaboradores` (presupuesto/OT) | `work_order_collaborators` |
| `presupuestos` | `work_orders` (`stage=estimate`) |
| `ots` | `work_orders` (`stage=work_order`) |
| `solicitantes` | `requesters` |
| `presupuestos_lineas_facturacion` ∪ `ots_lineas_facturacion` | `work_order_lines` |
| `presupuestos_solicitados` ∪ `ot_tecnico` | `work_order_technicians` |
| `chequeos` | `work_order_checklist_completions` |
| `archivos` (presupuesto/OT) | `work_order_attachments` |
| `acciones` | `actions` |
| `accion_usuarios` | `user_action_scores` |
| `kpi_configuraciones` | `kpi_configurations` |
| `historial_qcoins` | `quality_score_ledger` |
| `tipos_felicitacion` | `compliment_types` |
| `felicitaciones` | `compliments` |
| `felicitacion_usuario` | `compliment_user` |
| `archivos` (felicitacion) | `compliment_attachments` |
| `formularios_tipos` | `form_types` |
| `formularios_estados` | `form_statuses` |
| `biblias` | `form_bibles` |
| `plantillas` | `form_templates` |
| `secciones_plantillas` | `form_template_sections` |
| `campos_plantillas` | `form_template_fields` |
| `establecimiento_plantilla` | `establishment_form_template` |
| `ots_tipos_plantillas` | `work_order_type_form_templates` |
| `formularios` | `forms` |
| `secciones_formularios` | `form_sections` |
| `campos_formularios` | `form_fields` |
| `filtros` | `saved_filters` |
| brand messages | `brand_messages` |
| company schedules | `company_schedules` |
| company ↔ priority | `company_priority` |

**Column-only (not a table rename):** `ots.iteracion_id` → `work_orders.contract_iteration_id`.

---

## 2. New tables (not in legacy as that table)

| New (v2) | Why |
| --- | --- |
| `company_relationships` | New party-role model (replaces role side of clientes/proveedores/tecnicos) |
| `company_user` | Staff ↔ operating company membership |
| `numbering_patterns` | Document code patterns |
| `field_helps` (+ translations) | In-app field help |
| `integrations` | Integrations catalog |
| `tenants` | Multi-tenant |
| `sso_providers` / tenant SSO settings / user SSO identities | SSO |
| Spatie `roles` / `permissions` / pivots | Authz (replaces permiso sprawl) |
| `personal_access_tokens` | Sanctum |
| Framework: `jobs`, `cache`, … | Infra |

Also “new as a table shape” (legacy was polymorphic or two tables):

| New shape | Legacy source |
| --- | --- |
| `work_orders` | merge of `presupuestos` + `ots` |
| Typed `*_collaborators` | split of `colaboradores` |
| Typed `*_attachments` | split of `archivos` |
| Dedicated `*_statuses` catalogs | split of `estados` |

---

## 3. Skipped tables

### Deferred

| Old | Why |
| --- | --- |
| `visitas` | Planning / visits |
| `planificacion_lineas` | Planning lines |
| `preferencia_plantillas` | Template preferences |
| `ticket_plantillas` | Ticket templates |
| `plantillas_outlook` | Outlook templates |
| `chats` / `lineas_chats` | Messaging |
| `historial_cambios_estados` | Status audit |
| `ot_visita` | OT visits |
| `peticiones` | Requests |
| `prl_tickets` | PRL |
| `avisos_app` | App notices |
| OOH / nora / graph-email OT tables | Ops side-channels |

### Ignored / out of scope

| Old | Why |
| --- | --- |
| `facturas_*` (+ invoice/treasury/stock) | No billing domain in v2 yet |
| CRM `companies` / `contacts` / `deals` / `workplaces` | HubSpot overlay — **not** v2 `companies` |
| `modelos` | Morph catalog → typed enums |
| `plataformas` | Int on `forms.app_platform_id` only |
| `vista_*` (e.g. `vista_felicitaciones`) | SQL views |
| `kpis_diarios` | KPI snapshots |
| `plantillas_repetibles` | Unused |
| `permiso_accions` (+ permiso sprawl) | Spatie / policies |
| `presupuestos_trabajos_a_realizar` | Dead leftover |
| `presupuesto_estados` / `estados_ot` / `estimates` | Dead leftover catalogs |
| `technician_workorder` | Dropped 2023 |
| `ot_tecnico_articulos` | Never existed |
| Unused `estados` IDs (invoices, …) | No matching v2 status table |
| FileMaker leftover id tables/columns | Not first-class |

### Unresolved (do not import yet)

| Old | Why |
| --- | --- |
| `clientes_users` | Client-portal users — no v2 table |
| `establecimientos_users` | Store portal users — no v2 table |
| `marcas_usuarios` | Brand user links vs `company_user` |
| `clientes_tecnicos` / `proveedores_clientes` | Cross-party links — no v2 table |
| `users.establecimiento_id` | Portal binding |

### Documented in older specs but not created as v2 tables yet

These appear in [01-table-mapping.md](01-table-mapping.md) as intended targets; they are **not** present as create migrations today (treat as not imported until schema exists):

| Old | Planned / discussed new | Status |
| --- | --- | --- |
| `incidencias_mensajes` (+ archivos) | incident messages / attachments | Not created |
| `tecnicos_incidencias_colaboradores` / severities / origins / message files | technician-incident children | Partial (messages + types + statuses only) |
| `contratos_tipos` | `contract_types` | Not created |
| `evaluaciones_tipos` / subtipos / notas / archivos / colaboradores | evaluation children | Only `evaluations` + `evaluation_statuses` |
| `vehiculos_tipos` | `vehicle_types` | Not created |
| `brand_collaborators` / `evaluation_collaborators` / `technician_incident_collaborators` | typed collaborators | Not created |
| `incidencias_origenes` / `incidencias_gravedades` as separate tables | origins / severities | Replaced by typed fields / `incident_priorities` |

---

## Related docs

| Doc | Focus |
| --- | --- |
| [01-table-mapping.md](01-table-mapping.md) | Narrative mapping |
| [09-work-orders.md](09-work-orders.md) | Estimates + OTs |
| [10-quality-scores.md](10-quality-scores.md) | QCoins |
| [11-compliments.md](11-compliments.md) | Felicitaciones |
| [12-saved-filters.md](12-saved-filters.md) | Filtros |
| [13-forms-and-templates.md](13-forms-and-templates.md) | Plantillas / formularios |
| [14-contract-iterations.md](14-contract-iterations.md) | Iteraciones |
