# Legacy → v2 data migration specification

**Status:** analysis only. No data has been copied. No production schema or rows have been modified.

This folder is a reviewable mapping spec. Another developer should be able to implement an importer from it without guessing. Where evidence is missing, the text says `UNRESOLVED — REQUIRES REVIEW`.

## Purpose

Document how operational data in the legacy Optima backend maps to the new Laravel + Inertia schema, **before** writing a migrator.

## Source project

| Item | Value |
| --- | --- |
| Path | `/var/www/achraf.es/optima_prod/optimaback` |
| Stack | Laravel (legacy Optima / FileMaker-era Spanish tables) |
| Models | 254 Eloquent models |
| Migrations | ~1295 files |
| Schema snapshot | `database/schema/mysql-schema.sql` (**stale** vs later 2025–2026 migrations) |

Companion UI: `/var/www/achraf.es/optima_prod/optimafront` (not a second database).

## Target project

| Item | Value |
| --- | --- |
| Path | `/var/www/achraf.es/optima_v2_laravel_react` |
| Stack | Laravel 12 + Inertia + React |
| Models | 70 Eloquent models |
| Migrations | 73 files (as of this spec) |

## Databases

| Role | Evidence | Notes |
| --- | --- | --- |
| Source | Legacy MySQL used by `optimaback` | Live dump **not** taken in this phase. Column lists mix `mysql-schema.sql` + later migrations. |
| Target | v2 MySQL from `database/migrations` | Authoritative for the new schema. |

**Do not assume** the dump equals production. Re-dump before implementing.

## Migration principles

1. **No ID reuse for operational rows.** `legacy_id == new_id` is forbidden except for **catalogs whose seeders already force legacy IDs**.
2. **Do not invent mappings.** Unclear items stay unresolved.
3. **Identity is not a table rename.** Clients, suppliers and technicians become `companies` **plus** typed `company_relationships`.
4. **Preserve provenance.** Use `companies.legacy_erp_id`, mapping tables, and `company_relationships.legacy_status_id` where defined.
5. **Do not migrate domains that have no v2 tables yet** (invoices, CRM overlay, tickets, stock, treasury). Work orders/estimates **do** have `work_orders` — see [09-work-orders.md](09-work-orders.md).
6. **Reference data first.** Status/type catalogs are seeded with preserved IDs; operational FKs depend on them.

## Assumptions (explicit)

These are **working assumptions**, not proven production facts:

- Owner operating companies in v2 correspond to Optima legal entities (`empresas` / seeded OR + ORIL). Mapping every `empresas` row is **UNRESOLVED**.
- A customer store (`establecimientos`) belongs to the **related party company** created from `clientes`, not to the operating company.
- `company_user` membership is for **staff of the operating company**, not for client-portal users. Client-store user links (`establecimientos_users`, `clientes_users`) have **no equivalent v2 table**.
- Catalog IDs in v2 seeders (incident statuses 89…, technician incident statuses 96…, contract statuses 77…) match live legacy `estados` / type tables. Confirm against production before import.

## Known architectural changes

### Parties: three tables → company + relationship

**Legacy:** `clientes`, `proveedores`, `tecnicos` are separate legal-party tables. A brand (`marcas`) owns clients. Stores (`establecimientos`) belong to a client. Technician firms are not “companies” in the same table as clients.

**v2:**

```
operating company (companies.kind = operating_company)
    └── company_relationships.kind = customer | supplier | technician | partner
            └── related company (companies.kind = party)
                    └── establishments (for customers)
```

SLA, billing flags, technician scores, and “responsable” users live on **`company_relationships`**, not on `companies`.

### Statuses: polymorphic `estados` → dedicated catalogs

Legacy `estados` is a shared status table keyed by hardcoded IDs (`EstadosIncidenciasEnum`, contract states, technician-incident states, OT states). v2 uses one table per domain (`incident_statuses`, `contract_statuses`, `technician_incident_statuses`, …) and **seeds those IDs**.

### Collaborators: polymorphic → typed pivots

Legacy `colaboradores` (`user_id`, `relacion_id`, `modelo_id`). v2 has `brand_collaborators`, `company_relationship_collaborators`, `establishment_collaborators`, `incident_collaborators` only. Other modelo types have **no target**.

### Two incident domains stay separate

| Legacy | v2 |
| --- | --- |
| `incidencias` (QC/CX) | `incidents` |
| `tecnicos_incidencias` | `technician_incidents` (`technician_id` → `company_relationships`) |

### CRM overlay is not v2 companies

Legacy 2026 CRM `companies` / `contacts` / `workplaces` / `deals` sits **beside** `clientes`. v2 `companies` is the operational party model. **Do not** merge HubSpot CRM rows into v2 companies without a separate decision.

## Area overview

| Area | Legacy | New | Migration type | Status |
| --- | --- | --- | --- | --- |
| Operating orgs | `empresas` (+ seeded OR/ORIL) | `companies` (`operating_company`) | transform | pending — identity UNRESOLVED |
| Customers | `clientes` | `companies` (party) + `company_relationships` (customer) | split + merge | pending |
| Suppliers | `proveedores` | `companies` (party) + `company_relationships` (supplier) | split | pending |
| Technicians | `tecnicos` | `companies` (party) + `company_relationships` (technician) | split | pending |
| Users | `users` | `users` | transform | pending |
| Staff membership | implicit / roles | `company_user` | replace | pending — rule UNRESOLVED |
| Brands | `marcas` | `brands` | transform | pending |
| Establishments | `establecimientos` | `establishments` | transform | pending |
| Delegations | `delegaciones` | `delegations` | transform | pending |
| Countries / provinces / languages | `paises`, `provincias`, `idiomas` | `countries`, `provinces`, `languages` | rename + ID seed | pending |
| QC incidents | `incidencias` | `incidents` | transform | pending |
| Technician incidents | `tecnicos_incidencias` | `technician_incidents` | transform | pending |
| Contracts | `contratos` | `contracts` | transform | pending |
| Evaluations | `evaluaciones` | `evaluations` | transform | pending |
| Articles | `articulos` + `articulo_cliente` | `articles` + `article_clients` | transform | pending |
| Vehicles | `vehiculos` | `vehicles` | transform | pending |
| Work orders / estimates | `ots` + `presupuestos` | `work_orders` (`stage`) | merge | schema ready — data pending |
| Legacy CRM companies | `companies` (2026 overlay) | not v2 `companies` | unresolved | do not import blindly |

## Documents

| File | Contents |
| --- | --- |
| [01-table-mapping.md](01-table-mapping.md) | Every relevant legacy table |
| [02-field-mapping.md](02-field-mapping.md) | Field-level mapping |
| [03-relationship-mapping.md](03-relationship-mapping.md) | FK and cardinality changes |
| [04-transformation-rules.md](04-transformation-rules.md) | Confirmed transforms only |
| [05-id-mapping-strategy.md](05-id-mapping-strategy.md) | Mapping tables; never assume IDs |
| [06-migration-order.md](06-migration-order.md) | Dependency order |
| [07-validation-rules.md](07-validation-rules.md) | Counts, uniques, orphans |
| [08-unresolved-records.md](08-unresolved-records.md) | Open questions |
| [09-work-orders.md](09-work-orders.md) | Merged estimates + OTs |
| [10-quality-scores.md](10-quality-scores.md) | QCoins / `accion_usuarios` |
| [11-compliments.md](11-compliments.md) | Felicitaciones / compliment types |
| [12-saved-filters.md](12-saved-filters.md) | Named list filter presets (`filtros`) |
| [13-forms-and-templates.md](13-forms-and-templates.md) | Plantillas + formularios (templates / filled forms) |
| [14-contract-iterations.md](14-contract-iterations.md) | Contract iterations (`iteraciones`) + invoicing aggregations |
| [15-table-inventory.md](15-table-inventory.md) | Old ↔ new names, v2-only tables, skipped tables |

Machine-readable mappings: `database/migration/mappings/`.

## Unresolved questions (must be answered before import)

1. May two legacy rows that share a NIF (`clientes` + `proveedores`, etc.) become **one** `companies` row? Legacy has **no unique NIF**. v2 `tax_id` **is unique**.
2. Which `empresas` rows become `operating_company`? Only OR/ORIL, or all?
3. How is `company_user` populated? All `empleado_optima` users on every operating company? Per `delegacion`? Manual?
4. Where do client-portal users (`establecimientos_users`, `clientes_users`, `users.establecimiento_id`) go?
5. How does `clientes.estado` / `proveedores.activo` / `tecnicos.estado_id` map onto `company_relationships.status` (`prospect|active|blocked|inactive|archived`)?
6. Confirm production still has columns dropped in 2024 that models still list (`incidencias.establecimiento_id`, `tecnicos.activo`, `contratos.ots_tipo_id`).
7. Country/province/language ID alignment: seeders vs live `paises`/`provincias`/`idiomas`.
8. `Cliente::usuarios()` pivot table name vs `clientes_users`.

---

## Migration summary

Counts below cover **tables this spec classified**, not every one of the 254 legacy models.

### Tables

| Class | Approx. count | Notes |
| --- | --- | --- |
| Analyzed (business-relevant) | ~90 | Plus catalogs |
| Direct / rename catalogs | ~25 | Often ID-preserving via seeders |
| Merge / split (parties) | 4 sources → 2 targets | `clientes`/`proveedores`/`tecnicos`/`empresas` → `companies` + `company_relationships` |
| Transform (operational in v2) | ~20 | establishments, incidents, contracts, evaluations, articles, … |
| Deprecated / ignore / future | majority of OT, billing, CRM, tickets, stock | No v2 table |
| Unresolved | CRM `companies`, NIF merge, membership | See 08 |

### Fields (documented in 02)

| Class | Approx. |
| --- | --- |
| Direct / rename | ~120 (identity, flags, renamed FKs) |
| Transform | grouping enum, tax_id empty→NULL, origin morph, UUID public_id, date→timestamp |
| Computed | `companies.slug`, incident `establishment_id`/`evaluation_id` |
| Deprecated / ignored | OT/invoice domains, dead incident dates, contract cost columns, `minutos_reunion` |
| Unresolved | relationship `status`, `provincia`→`province_id`, NIF merge, membership, owner operating company |

### Relationships

| Class | Examples |
| --- | --- |
| Preserved (same idea, new FK) | establecimiento → cliente becomes establishment → company |
| Transformed | cliente.marca_id → relationship.brand_id; incidencia origin morph |
| New | `company_relationships`, `company_user`, typed collaborator pivots |
| Removed / no target | `ot_tecnico`, invoice lines, CRM deals, most `colaboradores` modelos |

### Risk areas

1. **Party identity / unique `tax_id`.** Highest data-loss or collision risk.
2. **`empresas` string PK** vs bigint `companies.id`.
3. **Staff vs client users** — easy to attach the wrong people to `company_user`.
4. **Polymorphic FKs** (`incidencias` origin/related, `colaboradores`, `archivos`).
5. **Stale schema dump** vs live columns.
6. **Catalog ID drift** if production `estados` IDs differ from seeders.
7. **Nullable → unique** (`tax_id`, `establishments.code`, `companies.slug`).
8. **Domains not in v2** — importing FKs to work orders will fail.

### Required manual decisions

See [08-unresolved-records.md](08-unresolved-records.md). Nothing in this spec authorizes merging parties by NIF, attaching all users to OR, or importing CRM `companies` into v2 `companies`.
