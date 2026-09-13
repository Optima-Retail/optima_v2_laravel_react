# 01 — Table mapping

**Rule:** similar names are not a mapping. Meaning comes from models, FKs, and domain code.

**Migration types:** `direct` · `rename` · `merge` · `split` · `transform` · `aggregate` · `replace` · `deprecated` · `ignore` · `manual` · `unresolved`

---

## Summary table

| Legacy table | New table | Migration type | Data preserved | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| `empresas` | `companies` (`kind=operating_company`) | transform | identity + legal fields | pending | String PK. Which rows? **UNRESOLVED** |
| `clientes` | `companies` + `company_relationships` | split | yes, split across two tables | pending | Party vs customer profile |
| `proveedores` | `companies` + `company_relationships` | split | yes, split | pending | No unique NIF in source |
| `tecnicos` | `companies` + `company_relationships` | split | yes, split | pending | `es_tecnico` / `es_acreedor` flags |
| `users` | `users` | transform | yes | pending | Still need ID map |
| `marcas` | `brands` | transform | yes | pending | `name` unique (uppercased on create) |
| `establecimientos` | `establishments` | transform | yes | pending | `cliente_id` → party `company_id` |
| `delegaciones` | `delegations` | transform | yes | pending | `empresa_id` string → company map |
| `paises` | `countries` | rename | yes | pending | Confirm IDs vs seeder |
| `provincias` | `provinces` | rename | yes | pending | Confirm IDs vs seeder |
| `idiomas` | `languages` | rename | yes | pending | Confirm IDs vs seeder |
| `zonas_horarias` | `timezones` | rename | yes | pending | |
| `equipos` | `teams` | rename | yes | pending | |
| `puestos` | `job_titles` | transform | yes | pending | Seeder skips id 15 |
| `estados` (subset) | many `*_statuses` | split | selected IDs only | pending | Polymorphic catalog |
| `incidencias` | `incidents` | transform | operational fields | pending | Origin morph rewritten |
| `incidencias_mensajes` | `incident_messages` | transform | yes | pending | |
| `incidencias_mensajes_archivos` | `incident_message_attachments` | transform | yes | pending | |
| `incidencias_colaboradores` | `incident_collaborators` | transform | yes | pending | |
| `incidencias_tipos` | `incident_types` | rename | yes | pending | ID-preserving seeder |
| `incidencias_gravedades` | `incident_severities` | rename | yes | pending | |
| `incidencias_origenes` | `incident_origins` | rename | yes | pending | |
| `tecnicos_incidencias` | `technician_incidents` | transform | yes | pending | `tecnico_id` → relationship |
| `tecnicos_incidencias_mensajes` | `technician_incident_messages` | transform | yes | pending | |
| `tecnicos_incidencias_mensajes_archivos` | `technician_incident_message_attachments` | transform | yes | pending | |
| `tecnicos_incidencias_colaboradores` | `technician_incident_collaborators` | transform | yes | pending | |
| `tecnicos_incidencias_tipos` | `technician_incident_types` | rename | yes | pending | |
| `tecnicos_incidencias_gravedades` | `technician_incident_severities` | rename | yes | pending | |
| `tecnicos_incidencias_origenes` | `technician_incident_origins` | rename | yes | pending | |
| `contratos` | `contracts` | transform | subset | pending | Several cost/date columns dropped in v2 |
| `contratos_tipos` | `contract_types` | rename | yes | pending | |
| `evaluaciones` | `evaluations` | transform | yes + `public_id` | pending | UUID generated |
| `evaluaciones_tipos` | `evaluation_types` | rename | yes | pending | |
| `evaluaciones_subtipos` | `evaluation_subtypes` | rename | yes | pending | |
| `evaluaciones_colaboradores` | `evaluation_collaborators` | transform | yes | pending | |
| `evaluaciones_notas` | `evaluation_notes` | transform | yes | pending | |
| `evaluaciones_notas_archivos` | `evaluation_note_attachments` | transform | yes | pending | |
| `evaluaciones_archivos` | `evaluation_attachments` | transform | yes | pending | |
| `articulos` | `articles` | transform | yes | pending | |
| `articulo_cliente` | `article_clients` | transform | yes | pending | `cliente_id` → **customer relationship** id |
| `vehiculos` | `vehicles` | transform | yes | pending | |
| `vehiculos_tipos` | `vehicle_types` | rename | yes | pending | |
| `colaboradores` | typed `*_collaborators` | split | **partial** | pending | Only some `modelo_id` values |
| `clientes_users` | none | unresolved | no | UNRESOLVED | Client-portal vs staff |
| `establecimientos_users` | none | unresolved | no | UNRESOLVED | |
| `marcas_usuarios` | none / `company_user`? | unresolved | no | UNRESOLVED | |
| `clientes_tecnicos` (if present) | *(no v2 table)* | unresolved | no | UNRESOLVED | No `company_relationship_links` in v2 |
| `proveedores_clientes` | *(no v2 table)* | unresolved | no | UNRESOLVED | Confirm whether used |
| `ots` | `work_orders` (`stage=work_order`) | merge | yes | schema ready | Same table as estimates; `confirmed_at` set |
| `presupuestos` | `work_orders` (`stage=estimate`) | merge | yes | schema ready | Confirm in place going forward |
| `solicitantes` | `requesters` | transform | yes | schema ready | `cliente_id` → party company |
| `ots_lineas_facturacion` / `presupuestos_lineas_facturacion` | `work_order_lines` | merge | yes | schema ready | Keep `sort_order` |
| `ot_tecnico` / `presupuestos_solicitados` | `work_order_technicians` | merge | yes | schema ready | `tecnico_id` → technician relationship |
| `chequeos` (OT/presupuesto) | `work_order_checklist_completions` | transform | yes | schema ready | |
| `acciones` | `actions` | rename | yes | schema ready | QCoins catalog; ID-preserving seeder |
| `accion_usuarios` | `user_action_scores` | transform | yes | schema ready | modelo morph → document_type + document_id; see [10-quality-scores.md](10-quality-scores.md) |
| `kpi_configuraciones` | `kpi_configurations` | rename | yes | schema ready | KPI bands for quality scores |
| `historial_qcoins` | `quality_score_ledger` | rename | yes | schema ready | Credits `users.quality_score` |
| `tipos_felicitacion` | `compliment_types` | rename | yes | schema ready | ID-preserving seeder 1–3 |
| `felicitaciones` | `compliments` | transform | yes | schema ready | Morph → typed subject FKs; see [11-compliments.md](11-compliments.md) |
| `felicitacion_usuario` | `compliment_user` | rename | yes | schema ready | Pivot `puntuacion` → `score` |
| `archivos` (felicitacion) | `compliment_attachments` | transform | yes | schema ready | Typed; no morph |
| `vista_felicitaciones` / `kpis_diarios` | none | ignore / defer | no | ignore | SQL view / KPI snapshots |
| `filtros` | `saved_filters` | transform | yes | schema ready | `modelo_id` → `page_key`; main lists only — see [12-saved-filters.md](12-saved-filters.md) |
| `presupuestos_trabajos_a_realizar` / `presupuesto_estados` / `estados_ot` | none | deprecated | no | ignore | Dead leftover |
| `facturas_*` | none | ignore | no | out of scope | |
| `companies` (CRM 2026) | **not** v2 `companies` | unresolved | n/a | do not import | HubSpot overlay |
| `contacts` / `deals` / `workplaces` | none in operational v2 | ignore | no | out of scope | CRM overlay |

---

## Parties (architectural transformation)

### What the old model represented

| Table | Business meaning | Source of truth |
| --- | --- | --- |
| `empresas` | Optima’s own legal entities. **String primary key.** | `Empresa` model (`$keyType = string`) |
| `clientes` | Customer legal entity. **Always has `marca_id`.** Operational SLA, billing, responsables live here. | `Cliente` + validations |
| `proveedores` | Supplier legal entity. | `Proveedor` |
| `tecnicos` | Technician / creditor firm. Flags `es_tecnico`, `es_acreedor`. | `Tecnico` |

These are **not** rows in one table. A NIF can appear on more than one table. There is **no unique constraint** on NIF.

### What the new model represents

| Table | Meaning |
| --- | --- |
| `companies` | One organization. `kind`: `operating_company`, `corporation`, `holding`, `ute`, `party`. Unique `slug`, unique `tax_id` (among non-deleted). |
| `company_relationships` | Directed role: `owner_company_id` → `related_company_id`, `kind` customer/supplier/technician/partner. Unique `(owner, related, kind, deleted_token)`. **Customer/technician profile fields live here.** |

### Mapping

1. Create a `companies` row for each migrated party (unless a reviewed merge policy says otherwise).
2. Create a `company_relationships` row linking the **operating company** to that party with the correct `kind`.
3. Copy identity fields (name, tax_id, address, email, phone, geo) onto `companies`.
4. Copy operational profile onto `company_relationships`.
5. **Do not auto-merge** `clientes`/`proveedores`/`tecnicos` that share a NIF. See [08-unresolved-records.md](08-unresolved-records.md).

### `empresas` → `companies`

| | |
| --- | --- |
| Type | transform |
| Data preserved | name/tax identity if those columns exist on live schema |
| Unresolved | Full column list vs seeder-only OR (`B66409087`) and ORIL (`IE4115369FH`). Delegation `empresa_id` is a **string**. |

---

## Users and membership

### `users` → `users`

Same table name; **still not the same IDs**. Transform: `empleado_optima` → `is_internal_employee`, `activo` → `is_active`, `equipo_id` → `team_id`, `zona_horaria_id` → `timezone_id`. Unique `email` and `username`.

### Membership (legacy has no `company_user`)

v2 requires `company_user` (`company_id`, `user_id` unique **without** a deleted-token — soft-delete blocks re-attach).

**UNRESOLVED — REQUIRES REVIEW:** how to derive membership from `empleado_optima`, roles, `marcas_usuarios`, or `delegaciones`.

### Client-facing user links — no v2 table

`clientes_users`, `establecimientos_users`, `users.establecimiento_id`: **unresolved**. Do not dump them into `company_user`.

---

## Brands, sites, delegations

### `marcas` → `brands`

Customer-facing brand. v2 unique `brands.name` (application uppercases on store). FK `company_id` = **operating company**, not the customer.

Legacy `Cliente::marca()` is NOT NULL. v2 `Establecimiento` model in legacy still has `marca()` but **no `marca_id` column** after later migrations — stores inherit brand via client.

### `establecimientos` → `establishments`

A physical/customer site. `cliente_id` → `establishments.company_id` via **customer party** map. Unique `(company_id, code)`. Billing party: `cliente_facturacion_id` → `billing_company_id`.

### `delegaciones` → `delegations`

Internal Optima offices. Seed includes ids 1 and **61**. `empresa_id` → operating `companies.id`.

---

## Catalogs (`estados` split)

Legacy `estados` is a single table. v2 seeders **keep selected IDs**:

| Domain | v2 table | Example preserved IDs |
| --- | --- | --- |
| Technician incidents | `technician_incident_statuses` | 96, 97, 98, 155, 156, 173 |
| QC incidents | `incident_statuses` | 89, 91, 92, 94, 150, 170 |
| Contracts | `contract_statuses` | 77, 95, 78, 79, 80 |
| Evaluations | `evaluation_statuses` | 73–76, 99, 117, 133, 134, 139 |

Other `estados` rows (invoice, …) have **no v2 table** → `ignore` until those domains exist.

OT and presupuesto statuses live in one `work_order_statuses` catalog (`kind` = `work_order` | `estimate`). Technician attendance 167–169 is `work_order_technician_statuses`.

**Exception to “never reuse IDs”:** these catalogs are **designed** to keep legacy IDs so historical FKs stay numeric. Confirm production IDs match seeders.

Job titles: seeder skips id **15**. Payment documents skip **3**. Estimate statuses skip **2**. Those legacy rows need a mapping table or exclusion list.

---

## Incidents (two domains)

Keep **separate**. Do not merge `incidencias` into `technician_incidents`.

`incidencias.establecimiento_id` / `evaluacion_id` were dropped in 2024-10-10; fillable still lists them. **UNRESOLVED** whether production still has them. v2 `incidents.establishment_id` is derived from origin (`evaluation` → establishment, or `establishment`).

---

## Contracts and evaluations

`contratos` → `contracts`: establishment + optional technician **relationship**. Several legacy cost/date columns have no v2 column → document as deprecated.

`evaluaciones` → `evaluations`: new required `public_id` (UUID). `cliente_id` → party company. Status via evaluation_statuses (preserved IDs).

---

## Articles and vehicles

`articulos` → `articles` (catalog). `articulo_cliente` → `article_clients` (`cliente_id` → **customer `company_relationships.id`**, not `companies.id`).

`vehiculos` → `vehicles`. Types via `vehicle_types`.

---

## Collaborators

| Legacy `modelo_id` (concept) | v2 table |
| --- | --- |
| Brand | `brand_collaborators` |
| Company relationship (client/tech/supplier) | `company_relationship_collaborators` |
| Establishment | `establishment_collaborators` |
| QC incident | `incident_collaborators` |
| Technician incident | `technician_incident_collaborators` |
| Evaluation | `evaluation_collaborators` |
| Presupuesto (1) / OT (2) | `work_order_collaborators` |
| Invoice, CRM, others | **no table** — ignore or manual |

`ModeloEnum`: Presupuesto=1, OT=2, Tecnico=3, Proveedor=4, Marca=5, Cliente=6, Establecimiento=7, TecnicoIncidencia=15, Evaluacion=21, Incidencia=22.

---

## Intentionally out of scope (no v2 table)

Sales/purchase invoices, tickets, stock, treasury, FileMaker leftovers, HubSpot CRM overlay, OT chats/visits/nora.

Work orders and estimates are **`work_orders`** — see [09-work-orders.md](09-work-orders.md). `tasks_to_perform.document_id` points at that table.

---

## Deprecated / ignore (selected)

| Legacy | Reason |
| --- | --- |
| `Tecnico::$fillable` `activo` | Column dropped; use `estado_id` → `legacy_status_id` |
| Duplicate CRM `companies` | Different domain |
| Many `estados` IDs | Not seeded in v2 |
| FileMaker IDs (`id_filemaker`, `id_fixner`) | `legacy_erp_id` on companies only; rest unresolved |

---

## Confidence (table level)

| Legacy | New | Confidence |
| --- | --- | --- |
| `clientes` → companies + customer relationship | high (architecture) | |
| Auto-merge by NIF | unresolved | |
| `tecnicos` → technician relationship | high | |
| `establecimientos` → establishments | high | |
| `marcas` → brands | high | |
| `users` → users | high | |
| `company_user` derivation | unresolved | |
| `incidencias` → incidents | high | |
| `ots` → anything | ignore / unresolved future | |
| CRM `companies` → v2 companies | unresolved (do not map) | |
