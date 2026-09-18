# 22 — Fresh test mapping (WSL source → V2 dest)

**Status:** importer implemented. Source is read-only. Destination mapping tables are created/repaired; operational rows are written only with `--execute`.  
**Supersedes** [01](01-table-mapping.md)–[08](08-unresolved-records.md) **for this test scope**. Those files are historical (written against a 73-migration V2 snapshot; this repo now has **115** migrations).

| | |
| --- | --- |
| Source | `legacy` ← `LEGACY_DB_*` (WSL Docker MySQL `127.0.0.1:3307`, database **`laravel`**). Read-only. |
| Dest | `migration_destination` ← `MIGRATION_DEST_DB_*` (`127.0.0.1:13311` + `MIGRATION_DEST_DB_SSL_CA`). |
| Command | `php artisan app:migrate-legacy` (dry-run) then `php artisan app:migrate-legacy --execute` |
| Probed | 2026-09-17 live source + live dest (OR `companies.id=1` tax `B66409087`) |

### Dest live seed (do not delete, adopt only)

| Table | Count | Known rows |
| --- | ---: | --- |
| `companies` | 5 | id=1 Optima Retail `B66409087` operating; id=2 ORIL |
| `users` | 2 | `admin@optima.test`=1, `user@optima.test`=2 |
| `brands` | 2 | ZARA=1, NIKE=2 |
| `company_relationships` | 4 | |
| `company_user` | 3 | |
| `establishments` | 2 | |
| `countries` / `languages` / `timezones` | 199 / 21 / 598 | copy-if-exists |
| `teams` | 24 | match `users.equipo_id` to `teams.code` |
| `delegations` | 1, 61 | most establishment/rel FKs NULL |
| `establishment_types` | 1, 2, 3 | |

Mapping tables on dest: `migration_user_map`, `migration_company_map`, `migration_company_relationship_map`, `migration_brand_map`, `migration_establishment_map`, `migration_quarantine`.

---

## 1. Legacy schema (live, not the stale dump)

`optimaback/database/schema/mysql-schema.sql` is stale. Live FKs and columns differ (no `clientes_users` / `establecimientos_users` tables; `clientes.company_id` exists as a CRM overlay unused on all live clientes).

### 1.1 Counts (non-deleted)

| Table | Live | Soft-deleted |
| --- | ---: | ---: |
| `users` | 37 470 | 1 357 |
| `clientes` | 2 879 | 6 |
| `tecnicos` | 17 802 | 324 |
| `marcas` | 551 | 2 112 (import 2 deleted that live clientes still reference) |
| `establecimientos` | 32 180 | 3 |
| `empresas` | 7 (string PK) | — |

### 1.2 Live FKs in scope

```text
marcas.id  <── clientes.marca_id          (NOT NULL, 0 orphans)
clientes.id <── establecimientos.cliente_id          (NOT NULL, 0 orphans)
clientes.id <── establecimientos.cliente_facturacion_id  (nullable; 2 rows ≠ cliente_id)
clientes.id <── clientes.cliente_reportado_id        (self; 991 live; 0 pointing at missing/deleted)
users.id    <── clientes.responsable_* / tecnicos.responsable_id / encontrado_por_id
users.id    <── marcas.responsable_id, responsable_comercial_id
users.id    <── establecimientos.responsable_id
users.id    <── users.manager_id, team_leader_id
equipos.id (varchar) <── users.equipo_id
marcas.id   <── users.marca_id
paises.id   <── clientes/tecnicos/establecimientos.pais_id
idiomas.id  <── * .idioma_id
delegaciones.id <── clientes/tecnicos/establecimientos.delegacion_id
estados.id  <── tecnicos.estado_id
tipos_establecimiento.id <── establecimientos.tipos_establecimiento_id

CRM overlay (ignore for V2 operational companies):
  companies.id <── clientes.company_id (0 populated)
  companies.id <── tecnicos.company_id (0)
  companies.id <── empresas.company_id
  companies.id <── marcas.corporation_company_id (0 live)
  companies.id <── establecimientos.customer_company_id / billing_customer_company_id
  workplaces.id <── establecimientos.workplace_id
```

There is **no** marca ↔ establecimiento column. `Establecimiento::marca()` is a stale Eloquent relation with **no** `marca_id` on the table. Brand reaches a store only through `establecimientos.cliente_id` → `clientes.marca_id`.

`provincias` table is **empty**. `clientes.provincia` / `tecnicos.provincia` / `establecimientos.provincia` are **varchar names** (FK to provincias was dropped 2023-08-21).

---

## 2. Table mapping (test scope)

| Source | Dest | Type | Rule |
| --- | --- | --- | --- |
| `users` | `users` | transform | New PKs. Import **all** non-deleted. |
| `clientes` | `companies` (`kind=party`) + `company_relationships` (`kind=customer`) | split + NIF merge | One legal entity → one company. |
| `tecnicos` | `companies` (`kind=party`) + `company_relationships` (`kind=technician`) | split + NIF merge | Same company as matching cliente when NIF valid. |
| — | `company_user` | new | **Only** `empleado_optima=1` → `company_id` of Optima Retail (verify id=1). |
| `marcas` | `brands` | transform | Adopt dest by `UPPER(name)`. |
| `establecimientos` | `establishments` | transform | `company_id` = party mapped from `cliente_id`. |
| `empresas` | operating `companies` | adopt only | Resolve `OR` by tax_id `B66409087`. Do not insert the other 6. |
| Legacy CRM `companies` | **not** V2 `companies` | ignore | Different domain. |

Out of test: `proveedores`, full `delegaciones`, work orders, incidents, invoices.

---

## 3. Column mapping

**Legend:** Map = resolve via mapping table. copy-if-exists = copy catalog id only when dest row exists, else NULL + log. R = required on dest.

### 3.1 `users` → `users`

Employee vs SSO (live flags):

| Flag combo | Live | Meaning |
| --- | ---: | --- |
| `empleado_optima=1`, `login_unicamente_sso=1` | 768 | **Employee** who logs in via SSO |
| `empleado_optima=1`, `login_unicamente_sso=0` | 8 | Employee, password login |
| `empleado_optima=0`, `login_unicamente_sso=1` | 36 688 | Portal/SSO user, **not** staff |
| both 0 | 6 | Neither |

**Do not** treat `login_unicamente_sso` as “not an employee”. 768/776 employees also have that flag. Membership uses **`empleado_optima` only**.

| Source | Type | Src FK | Dest | Type | Dest FK | Transform | Null | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` | bigint PK | | `id` | bigint PK | | **new ID** | no | `migration_user_map` |
| `name` | varchar | | `name` | varchar | | trim | no | |
| `email` | varchar unique | | `email` | varchar unique | | trim | no | 0 live dups. Adopt dest `admin@optima.test` / `user@optima.test` by email. |
| `username` | varchar unique null | | `username` | varchar unique null | | empty→NULL | yes | 6 291 filled, 0 dups |
| `password` | varchar | | `password` | varchar | | **copy `$2y$` hash** | no | All 38 827 are bcrypt. Do not re-hash. |
| `locale` | varchar | | `locale` | varchar(10) | | copy | yes | |
| `manager_id` | bigint | users | `manager_id` | bigint | users | user map, pass 2 | yes | 0 orphans |
| `team_leader_id` | bigint | users | `team_leader_id` | bigint | users | user map, pass 2 | yes | |
| `equipo_id` | **varchar** | equipos.id | `team_id` | bigint | teams | match `teams.code` = `equipos.id` | yes | Never copy varchar into bigint. Seeder misses `EQ02`. 758/776 employees have a team. |
| `zona_horaria_id` | bigint | zonas_horarias | `timezone_id` | bigint | timezones | copy-if-exists | yes | |
| `marca_id` | bigint | marcas | `brand_id` | bigint | brands | brand map, pass 2 | yes | 0 employees have marca; 18 907 non-employees do |
| `telefono` | varchar | | `phone` | varchar(50) | | trim; >50 → NULL+log | yes | |
| `ringover_telefono` | bigint | | `telephony_phone_number` | varchar(30) | | stringify | yes | |
| `pbx_extension` | varchar | | `pbx_extension` | varchar(20) | | copy | yes | |
| `telegram_user_id` | varchar | | `telegram_user_id` | varchar | | copy | yes | |
| `external_hr_id` | varchar | | `external_hr_id` | varchar | | copy | yes | |
| `activo` | tinyint | | `is_active` | boolean | | rename | no | 572 inactive **employees** |
| `empleado_optima` | tinyint | | `is_internal_employee` | boolean | | rename | no | **776** true → `company_user` |
| `es_usuario_equipo` | tinyint | | `is_team_account` | boolean | | rename | no | |
| `es_preventivo` | tinyint | | `is_preventive_specialist` | boolean | | rename | yes | |
| `factor_x` | decimal | | `performance_factor` | decimal(5,2) | | rename | no | |
| `objetivo_eur_facturados` | decimal | | `invoiced_revenue_target` | decimal | | rename | yes | |
| `puntuacion_qc` | double | | `quality_score` | decimal(12,2) | | rename | no | |
| `saldo` | int | | `balance` | decimal(12,2) | | rename | no | |
| `budget_approval_limit` | decimal | | `budget_approval_limit` | decimal | | copy | no | dest default 0 |
| `login_unicamente_sso` | tinyint | | `sso_only` | boolean | | rename | no | Copy as-is. Not a membership filter. |
| `must_change_password` | tinyint | | `must_change_password` | boolean | | copy | no | |
| `totp_secret` | mediumtext | | `totp_secret` | text | | copy | yes | |
| `bloqueado_at` | timestamp | | `locked_at` | timestamp | | rename | yes | |
| `email_verified_at` | | | `email_verified_at` | | | copy | yes | |
| `remember_token` | | | `remember_token` | | | copy | yes | |
| timestamps | | | timestamps | | | copy | | |
| — | | | `active_company_id` | bigint | companies | Optima Retail **iff employee** | yes | After `company_user`. `ActiveCompany` requires membership. |
| `establecimiento_id` | bigint | establecimientos | — | | | **NOT MIGRATED** | | No V2 column. 82 live rows. |
| `tenant_id` | bigint | tenants | `tenant_id` | bigint | tenants | copy-if-exists else NULL | yes | Do not invent tenants |
| `ringover_key` | varchar | | — | | | **NOT MIGRATED** | | No V2 column |
| `id_fixner` | varchar | | — | | | **NOT MIGRATED** | | |

Insert pass 1: `manager_id`, `team_leader_id`, `brand_id`, `active_company_id` NULL.

### 3.2 Party identity → `companies` (from clientes **or** tecnicos)

| Source | Dest | Transform | Merge | Null |
| --- | --- | --- | --- | --- |
| `clientes.id` / `tecnicos.id` | `companies.id` | new ID | both maps may share `new_id` | no |
| `clientes.razon_social` / `tecnicos.nombre_fiscal` | `name` | trim; fallback commercial name | if merge: prefer cliente legal name | no |
| `nombre_comercial` | `tradename` | trim | prefer non-empty | yes |
| — | `slug` | `Company::uniqueSlugFromName` | one slug per company | no |
| `nif` | `tax_id` | §5 normalize | one tax_id per merged company | yes if invalid |
| — | `kind` | `party` | | no |
| `pais_id` | `country_id` | copy-if-exists | prefer cliente | yes |
| `tecnicos.residence_country_id` | `residence_country_id` | copy-if-exists | technician only | yes |
| `tecnicos.person_type` | `person_type` | `F`/`J` | technician; cliente NULL | yes |
| `email` / `correo` | `email` | trim | prefer cliente | yes |
| `telefono` | `phone` | trim; max 50 | | yes |
| `tecnicos.pagina_web` | `website` | trim | | yes |
| `direccion_1/2` | `address_line_*` | trim | | yes |
| `poblacion` | `city` | trim | | yes |
| `provincia` | `province_id` | **always NULL** | text, not FK | yes |
| `codigo_postal` | `postal_code` | trim; max 20 | | yes |
| `idioma_id` | `language_id` | copy-if-exists | | yes |
| `tecnicos.numero_empleados` | `employee_count` | | | yes |
| `tecnicos.latitud/longitud` | `latitude/longitude` | decimal | clientes have no coords | yes |
| — | `is_active` | default true | role status is on relationship | no |
| `marca_id` | `companies.brand_id` | **do not set** | brand belongs on **customer relationship** | yes |
| `uuid` / `codigo` / `id_fixner` / CRM `company_id` | — | **NOT MIGRATED** | | |

### 3.3 `clientes` → `company_relationships` (`kind=customer`)

Owner = Optima Retail (verify `id=1` ∧ `tax_id=B66409087`). Related = party from clientes map.

| Source | Dest | Transform | Notes |
| --- | --- | --- | --- |
| — | `owner_company_id` | OR map | CHECK owner ≠ related |
| mapped party | `related_company_id` | company map `clientes` | |
| — | `kind` | `customer` | unique with owner+related+deleted_token |
| `estado` tinyint | `status` | **proposed** `1→active`, `0→inactive` | Live 2213 / 666. V2 has 5 values. Confirm. |
| `is_intercompany` | `classification` | `intercompany` else `commercial` | |
| `marca_id` | `brand_id` | **brand map** | Required in source. 4 live clientes → deleted marcas 22, 140: import those marcas. |
| `delegacion_id` | `delegation_id` | copy-if-exists else NULL | 0 nulls; 37 distinct; 1328 not in seeded `{1,61}` |
| `billing_language_id` | `billing_language_id` | copy-if-exists | |
| `serie_id` | `series_id` | copy-if-exists | |
| `integracion_id` | `integration_id` | copy-if-exists | |
| `integracion_relacion_id` | `integration_external_id` | stringify | |
| `cliente_reportado_id` | `reported_customer_relationship_id` | **relationship** map of the other cliente, pass 2 | Dest FK is `company_relationships.id`, **not** `companies.id`. Confirmed: migration, `CompanyRelationship`, `CompanyValidation`, FieldHelp. |
| `responsable_ots_correctivas_id` | `corrective_work_order_owner_id` | user map | Dest validation requires **company_user** of owner. Only employees will match; others → NULL + log. |
| `responsable_ots_preventivas_id` | `preventive_work_order_owner_id` | user map | same |
| `responsable_qc_id` | `quality_owner_id` | | |
| `responsable_cliente_id` | `account_owner_id` | | |
| `responsable_comercial_id` | `commercial_owner_id` | | |
| `observaciones` | `notes` | | |
| `observaciones_privadas` | `internal_notes` | | |
| `observaciones_aviso` | `notes_alert` | | |
| `observaciones_privadas_aviso` | `internal_notes_alert` | | |
| `onboarding` | `onboarding_notes` | | |
| `comentarios_facturacion` | `billing_comments` | | |
| `arquetipo` | `archetype` | | |
| grouping / billing / QC flags | matching `group_*`, `requires_*`, `is_franchise`, … | rename; enum `cliente→customer`, `establecimiento→establishment`, `ot→work_order` | see [02](02-field-mapping.md) §2b — still valid vs current `CompanyRelationship` fillable |
| `dias_cierre_presupuesto` | `quote_close_days` | int | |
| `meetings_frequency_*` | `recurring_meeting_frequency` / `sales_feedback_meeting_frequency` | | |
| `facturacion_revisada` | `is_invoicing_reviewed` | | `invoicing_reviewed_at` NULL |
| `correo_revisado` | `is_email_reviewed` | | |
| `codigo` | — | **NOT MIGRATED** | V2 uses relationship id |
| `tipo_valoracion_id` | — | **NOT MIGRATED** | no V2 column |
| `company_id` (CRM) | — | **NOT MIGRATED** | 0 rows |

### 3.4 `tecnicos` → `company_relationships` (`kind=technician`)

| Source | Dest | Transform |
| --- | --- | --- |
| — | `kind` | `technician` |
| `estado_id` | `legacy_status_id` | copy (lossless) |
| `estado_id` | `status` | **proposed default `active`** until a catalog map is approved. Live: Filtraje 4995, NO USAR 4034, Activo 3559, … |
| `delegacion_id` | `delegation_id` | copy-if-exists |
| `impuesto` | `tax_rate` | |
| `tarifas` | `rates_notes` | |
| `puntuacion_or/cliente/media` + `_num` | `optima_score` / `customer_score` / `average_score` + counts | |
| `prl` | `has_health_and_safety` | |
| `es_tecnico` | `is_field_technician` | 16232 only-tech, 1367 only-creditor, 203 both |
| `es_acreedor` | `is_creditor` | |
| `es_vip` / `disponible_24h` / `tiene_embargo` | `is_vip` / `is_available_24h` / `has_garnishment` | |
| `hora_dia_inicio/fin` | `day_start_at` / `day_end_at` | |
| `whatsapp_message_authorisation` | `whatsapp_messaging_authorized` | |
| `encontrado_por_id` | `sourced_by_user_id` | user map; membership rule as above |
| `responsable_id` | `account_owner_id` | best-fit |
| `revisado` | `is_reviewed` | |
| `fecha_alta` | `registered_at` | |
| `codigo` / `codigo_tecnico` | — | **NOT MIGRATED** |

### 3.5 `marcas` → `brands`

Live: 551 names, **all already uppercase**, 0 duplicate names. Natural key = `UPPER(TRIM(name))`. Dest seeder has `ZARA`, `NIKE` — **adopt**, write map, do not insert duplicates. `StoreBrandRequest` uppercases on create; importer must uppercase too.

| Source | Dest | Transform |
| --- | --- | --- |
| `id` | `id` | new ID + `migration_brand_map` (or dest id if adopted) |
| `nombre` | `name` | `strtoupper(trim)` |
| `responsable_id` | `account_manager_id` | user map |
| `responsable_comercial_id` | `commercial_manager_id` | user map |
| `periodicidad_reunion_fidelizacion` | `loyalty_meeting_frequency` | |
| `contactable_qc` | `is_quality_control_contactable` | |
| `send_debt_reminders` | `send_debt_reminders` | |
| `minutos_reunion` | — | **NOT MIGRATED** |
| `corporation_company_id` | `corporation_company_id` | **NOT MIGRATED** this test (0 live; FK to legacy CRM `companies`) |
| `uuid` | — | **NOT MIGRATED** |

Also import deleted marcas **22** (CANDIDO HERMIDA) and **140** (EASILITY IBERICA) because 4 live clientes still FK them.

### 3.6 `establecimientos` → `establishments`

| Source | Dest | Transform |
| --- | --- | --- |
| `id` | `id` | new + `migration_establishment_map` |
| `cliente_id` | `company_id` | **party company** from clientes map | R. Not Optima Retail. |
| `cliente_facturacion_id` | `billing_company_id` | party company from **that** cliente (also through NIF merge). Dest FK is `companies.id`, **not** a relationship. FieldHelp: “legal company invoiced when it differs from operational client”. 2 live rows differ. |
| `nombre` | `name` | trim | R |
| `codigo` | `code` | empty→NULL; unique `(company_id, code)` | 1 empty, 0 dup pairs |
| `codigo_tienda` / `_2` | `store_code` / `alternate_store_code` | |
| phone/email/emails/address/city/postal | renamed columns | |
| `provincia` | `province_id` | **NULL** |
| catalog FKs | matching | copy-if-exists (types 1–3 match seeder) |
| `responsable_id` | `responsible_user_id` | user map |
| `estado` | `is_active` | boolean |
| flags/notes/coords/iva/nora JSON | as current `Establishment` fillable | |
| `billing_customer_company_id`, `customer_company_id`, `workplace_id` | — | **NOT MIGRATED** (CRM overlay) |

### 3.7 `company_user`

| Dest | Value |
| --- | --- |
| `company_id` | Optima Retail (verify id=1) |
| `user_id` | mapped user **where `is_internal_employee`** |
| `is_active` | `users.is_active` |
| unique | `(company_id, user_id)` **including soft-deleted** — upsert + restore |

SSO-only non-employees: **no row**.

---

## 4. Relationship mapping

```text
OLD
marcas 1──< clientes 1──< establecimientos
                 └── cliente_facturacion_id ──> (other) clientes
                 └── cliente_reportado_id ──> (other) clientes
tecnicos  (no marca)

NEW
brands 1──< company_relationships.kind=customer.brand_id
operating_company (OR)
    ├── company_user ── employees only
    └── company_relationships
          kind=customer  related=party(cliente)  reported=other customer RELATIONSHIP
          kind=technician related=party(tecnico)   [same party if NIF merge]
party companies 1──< establishments.company_id
                     establishments.billing_company_id ──> party(cliente_facturacion)
```

`reported_customer_relationship_id` → **relationship id**.  
`billing_company_id` → **company id**.

Unique `(owner, related, kind, deleted_token)` means **one customer relationship per party per owner**. Duplicate clientes that are truly different operational profiles cannot share one company.

---

## 5. NIF merge strategy

Normalize: `UPPER(TRIM(nif))`, strip spaces and hyphens.  
Invalid (do **not** merge, store `tax_id=NULL`): empty, `X`, `FALTA`, `#FALTA`.  
Live valid NIFs: 2 671 clientes, 16 426 tecnicos.

### 5.1 Cross-table (cliente ∩ tecnico) — **MERGE**

187 distinct valid NIFs (202 join rows). User rule: **same valid NIF → same `companies` row**, two relationships.

Name mismatches (96/202) are mostly punctuation / `EMBARGO-` / `no pagar` prefixes — still the same legal entity. Merge is authorized.

```text
cliente 123 ─┐
             ├── companies N
tecnico 456 ─┘
N ├── customer relationship (from 123)
  └── technician relationship (from 456)
```

`migration_company_map (clientes,123)` and `(tecnicos,456)` share `new_id`.  
Relationship maps stay **separate**.

### 5.2 Within-table duplicates — **do not silent-merge mixed names**

V2 unique `(owner, related, kind)` allows only **one** customer (or technician) relationship per party.

| Class | Groups | Rows | Action |
| --- | ---: | ---: | --- |
| Clientes, same NIF, **same** normalized name | 13 | | Merge to one company **and one** customer relationship. Both old ids map to the same company_id **and** same relationship_id. |
| Clientes, same NIF, **different** names | 74 | e.g. MACRO AUSTRIA vs MACRO PORTUGAL on `IE9679669H`; DAINESE regional lotes; ALTAFIT LOTEs | **AMBIGUOUS.** Do not merge. First keeps `tax_id`; others `tax_id=NULL` + quarantine. Separate companies so each can have its own customer relationship. |
| Tecnicos, same name | 3 | | Merge like same-name clientes. |
| Tecnicos, mixed names | 36 | | Ambiguous; same as mixed clientes. |
| NIF length > 32 | 2 tecnicos | | `tax_id=NULL` + quarantine. |

Identity resolution runs **before** inserts (in-memory plan). Dry-run prints every ambiguous group.

---

## 6. ID mapping

| Table | Unique | Cardinality |
| --- | --- | --- |
| `migration_user_map` | `legacy_id` | 1:1 |
| `migration_company_map` | `(legacy_source, legacy_id)` | many source rows → **one** `new_id` when merged |
| `migration_company_relationship_map` | `(legacy_source, legacy_id)` | 1:1 per source row (except same-name within-table dups sharing one relationship) |
| `migration_brand_map` | `legacy_id` | 1:1 (adopt dest id when name matches) |
| `migration_establishment_map` | `legacy_id` | 1:1 |
| `migration_quarantine` | | conflicts |

`empresas.id='1'` is **not** Optima Retail. Map `OR` → dest company with tax_id `B66409087`.

Idempotency: skip if map exists and dest row exists; adopt seed rows by natural key; `company_user` upsert including trashed.

---

## 7. FK dependency order (verified)

Circular: `users.brand_id` ↔ `brands.account_manager_id`; `users` self-FKs; `reported_customer_relationship_id` self-FK.

```text
0. Dest probe: SSL, companies.id=1 tax_id, catalogs present. STOP on mismatch.
1. Users pass 1 (self-FKs and brand_id NULL)
2. Adopt Optima Retail (no insert if present)
3. Brands (managers nullable; adopt ZARA/NIKE)
4. Users pass 2: manager, team_leader, brand, team by code
5. Identity plan (NIF index) — no dest write
6. Insert/adopt unique party companies
7. Customer relationships (reported NULL)
8. Technician relationships
9. Backfill reported_customer_relationship_id
10. company_user for employees only
11. users.active_company_id for those members
12. Establishments
13. Validate
```

Catalog FKs are optional (copy-if-exists). Delegations are **not** imported in this test → most `delegation_id` will be NULL.

---

## 8. Diff vs old mapping docs (01–08)

| Old assumption | Source now | Dest now | Mapping |
| --- | --- | --- | --- |
| Do **not** merge NIF | 187 valid cliente∩tecnico NIFs; same legal names | unique `tax_id`; unique (owner,related,**kind**) | **Merge across tables.** Within-table mixed names stay separate. |
| `company_user` UNRESOLVED / all users | 776 employees vs 36 688 SSO non-employees; 768 employees also `sso_only` | `CompanyMemberUsers` = staff picker | **Employees only** → company 1. Copy `sso_only` as a flag, not a membership filter. |
| 73 V2 migrations | — | **115** migrations | Party tables unchanged since 2026-09-04; extra tables out of test. |
| `mysql-schema.sql` has `clientes_users` | Tables **absent** live | no portal pivot | Ignore. |
| Brand uppercase “on create” vaguely | All 551 live names already UPPER | `StoreBrandRequest` `strtoupper` | Importer uppercases; match dest by upper name. |
| `equipo_id` → `team_id` as id copy | `equipos.id` **varchar** (`ADM`,`EQ01`,…) | `teams.id` bigint, seeder by `code`, missing `EQ02` | Match `teams.code`. |
| `provincia` type unknown | varchar names; `provincias` empty; FK dropped 2023 | `province_id` FK | NULL. |
| reported → relationship id | `cliente_reportado_id` → clientes | `reported_customer_relationship_id` → `company_relationships` | Relationship id. Confirmed. |
| billing → party company | `cliente_facturacion_id` → clientes | `billing_company_id` → **companies** | Company id, via clientes map. |
| Dest empty-ish | — | Seed: OR/ORIL, admin, ZARA/NIKE, demo clients/sites | Adopt, never delete. |
| Source named `optima_prod` | Database **`laravel`** | — | Confirmed as the intended dump. |

---

## 9. Diff vs “server-side V2 analysis”

The server-side analysis **is** `docs/data-migration/01–20` (README paths `/var/www/achraf.es/...`, 70 models / 73 migrations). Re-checked against **this** repo’s models/migrations:

| Topic | Server doc | Current V2 code | Verdict |
| --- | --- | --- | --- |
| Party split (identity vs relationship) | yes | `Company` vs `CompanyRelationship` fillable + `CompanyValidation::relationshipProfileRules` | Keep |
| `reported_customer_relationship_id` | relationship id | constrained to `company_relationships`; FieldHelp “parent customer relationship” | Keep |
| `establishments.billing_company_id` | company id | `constrained('companies')`; FieldHelp “legal company invoiced” | Keep |
| Brand on relationship | yes | `Brand::customers()` = relationships where kind=customer | Keep; do not put marca on `companies.brand_id` for this import |
| Owner user FKs must be members | implied | `CompanyMemberUsers::existsRule` on all `*_owner_id` | Non-employee responsables → NULL |
| `company_user` unique without deleted_token | yes | still true in `2026_09_04_130000` | Upsert including trashed |
| NIF no-merge | 08 | unique tax_id still | **Override** for valid cross-table NIF per current product rule |
| Catalog ID seeders | countries/languages/types preserve ids | same seeders | copy-if-exists |
| Dest live counts | never queried | still not queried from WSL | Probe before `--execute` |

---

## 10. Remaining ambiguities

1. **Dest SSL** — connected via `migration_destination` (`127.0.0.1:13311` + CA). Company id 1 is Optima Retail. 
2. **Relationship status** — proposed maps above; not in V2 code.  
3. **74 mixed-name client NIF groups** (MACRO/DAINESE/ALTAFIT lotes) — listed in dry-run; no auto-merge.  
4. **36 mixed-name technician NIF groups** — same.  
5. **Delegations** — not in test; most `delegation_id` NULL.  
6. **Owner always OR** — flattens ORIL/Bilda history.  
7. **`EQ02` and other teams** missing from `TeamSeeder` → `team_id` NULL.  
8. Responsable users who are not employees cannot stay on relationship owner FKs under current validation.

---

## 11. Implementation

| Piece | Design |
| --- | --- |
| Command | `php artisan app:migrate-legacy` |
| Default | dry-run (mapping tables created/repaired; no operational inserts) |
| Execute | `--execute` required |
| Connections | `legacy` ← `LEGACY_DB_*`; `migration_destination` ← `MIGRATION_DEST_DB_*` + SSL CA |
| Layout | `app/Domain/DataMigration/LegacyMigrator.php`, `Nif.php` |
| Identity | Build NIF plan **before** company inserts |
| Chunks | `--chunk=200` (minimum 50) |
| Idempotent | mapping tables + email/brand-name adopt |
| Safety | never DELETE dest operational rows, never UPDATE source, never `SET FOREIGN_KEY_CHECKS=0`, never reuse source PKs |

---

## 12. Commands

From `optima_v2_laravel_react`:

```text
php artisan app:migrate-legacy
php artisan app:migrate-legacy --execute
```

Dry-run **SELECT**s source and dest, creates/repairs mapping tables if missing, prints the report, **does not insert users/companies/relationships/brands/establishments**.

`--execute` uses the same plan, then inserts, writes maps, and stores quarantine rows. It is idempotent via the mapping tables.
