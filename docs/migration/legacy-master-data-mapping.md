# Legacy master-data mapping

This document describes how FileMaker / V2 Optima party tables collapse into laravel_optima identity, membership, relationship, and site tables.

It is a **spec**, not an importer. Preserve `created_at` / `updated_at` / `deleted_at` when present.

## Canonical model

- **`companies`** — one legal party (unique `tax_id` among live rows). Global identity, not tenant-owned.
- **`company_user`** — user membership on a company. No per-company Spatie roles yet.
- **`users.active_company_id`** — last context; must still be a live membership.
- **`company_relationships`** — directional role of a related party *for* an owner (tenant = `owner_company_id`).
- **`establishments`** — company-owned sites. Merge old `workplaces` (physical) + `establecimientos` (store overlay). Do **not** nest workplaces under establishments.
- **`delegations`** — billing/ops catalog (config-only UI). Old `delegaciones`; establishments reference via `delegation_id`. Import before establishments.
- **`brands`** — remain global. `corporation_company_id` is optional.

Technicians are companies (`person_type` F/J). People on site are `operarios` (out of this pass).

## Identity: collapse into `companies`

Preferred source: **V2 `companies`**. Preserve `companies.id` on import.

FM profile tables still exist in production and also represent parties. Collapse by normalized tax id (`nif`):

| Old table | Role in collapse | Notes |
|---|---|---|
| `companies` (V2) | Canonical row | `nif` → `tax_id`, keep `id` |
| `empresas` | Operating / ledger aliases (OR, ORIL, …) | Reuse V2 company when NIF matches (`B66409087`, `IE4115369FH`) |
| `clientes` | Customer profile on a party | Do **not** dump 1:1 into `companies`; find/create party by NIF, then a `customer` relationship |
| `proveedores` | Supplier profile | Same: party by NIF + `supplier` relationship |
| `tecnicos` | Subcontractor *company* | Party by NIF + `technician` relationship |

If two FM rows share a NIF, keep one `companies` row (prefer V2 id). Store colliding FM ids in a later **`legacy_party_map`** only if needed. Do **not** add `legacy_id` to every table now.

Customer-only columns (`serie_id`, `po_requerida`, responsables, QC flags) stay off `companies`; they live on **`company_relationships`**.

## Membership: `companies_users` → `company_user`

| Old | New |
|---|---|
| `company_id` | `company_id` |
| `user_id` | `user_id` |
| (later `is_accounting_working`) | `is_active` (default true) |

Unique `(company_id, user_id)`. Spatie roles stay global.

After import, set `users.active_company_id` to the first active membership when null.

## Relationships: V2 `company_relationships` → `company_relationships`

V2 unique was `(provider_id, customer_id)` — one commercial edge. New unique is `(owner_company_id, related_company_id, kind)` so A can be both customer and supplier of B as **two rows**.

Transform a V2 provider/customer row into at least one new row:

- **Customer view (ledger as owner):** `owner_company_id = provider_id`, `related_company_id = customer_id`, `kind = customer`
- Optional inverse or extra kinds come from FM:
  - `clientes` → kind `customer` (owner = Optima operating company, related = party)
  - `proveedores` → kind `supplier` (same owner/related pair allowed)
  - `tecnicos` → kind `technician`

| V2 column | New column |
|---|---|
| `provider_id` | `owner_company_id` (when importing the customer-kind row) |
| `customer_id` | `related_company_id` |
| `provider_customer_code` | `owner_reference` |
| `customer_provider_code` | `related_reference` |
| `relationship_type` | `classification` |
| `status` | `status` |
| `notes`, `starts_at`, `ends_at`, `created_by`, `updated_by` | copy |
| `external_reference` | `external_code` (also `tecnicos.codigo_tecnico` when kind=technician) |

Reject `owner_company_id = related_company_id`.

FM `clientes` / `proveedores` / `tecnicos` extras that are tenant-role (not legal identity) land on **`company_relationships`** with English names. See `old-to-new-table-mappings.json` for the column list.

Party identity extras on **`companies`**: `language_id` (`idioma_id`), `latitude` / `longitude` (technician HQ), `legacy_erp_id` (`id_fixner`).

| Kind of field | Table | Examples |
|---|---|---|
| Legal identity / HQ | `companies` | name, tax_id, address, language, geo, Fixner id |
| Role for an owner | `company_relationships` | codes, notes, delegation, invoicing flags, responsables, scores, technician availability |

Skipped on purpose: `clientes.uuid`, `tecnicos.peticion_id` (no `peticiones` table). `tecnicos.estado_id` is kept as `legacy_status_id` without FK.

## Sites: `workplaces` + `establecimientos` → `establishments`

Old `workplaces` **are** the physical sites (`company_id`, address, `is_active`). Old `establecimientos` are the store profile on a customer (`cliente_id` + unique `workplace_id`).

One new row per workplace (preferred PK). Overlay store fields from `establecimientos` when `workplace_id` matches.

| Source | New |
|---|---|
| `workplaces.company_id` | `company_id` |
| `establecimientos.nombre` | `name` |
| `establecimientos.codigo` | `code` |
| `establecimientos.codigo_tienda` / `_2` | `store_code` / `alternate_store_code` |
| `establecimientos.telefono` / `correo` | `phone` / `email` |
| `establecimientos.correos` / `correos_destinatarios` | `emails` / `recipient_emails` |
| `workplaces.address` / FM address | `address_line_1` |
| `workplaces.town` | `city` |
| `workplaces.province` | `province` |
| `workplaces.cp` | `postal_code` |
| `workplaces.pais_id` / FM `pais_id` | `country_id` (remap) |
| `establecimientos.zona_horaria_id` | `timezone_id` |
| `establecimientos.idioma_id` | `language_id` |
| `establecimientos.tipos_establecimiento_id` | `establishment_type_id` |
| `establecimientos.delegacion_id` | `delegation_id` |
| `establecimientos.serie_id` | `series_id` |
| `establecimientos.cliente_facturacion_id` | `billing_company_id` (via party map) |
| `establecimientos.responsable_id` | `responsible_user_id` |
| `establecimientos.tipos_establecimiento_id` | `establishment_type_id` |
| `workplaces.is_active` / `estado` | `is_active` |
| `importante_cliente` | `is_client_priority` |
| `revisado` / `revisado_email` | `is_reviewed` / `is_email_reviewed` |
| `prl_centro` / `prl_cliente` | `has_site_health_and_safety` / `has_customer_health_and_safety` |
| `contactable_qc` | `is_quality_control_contactable` |
| `parking` / `ulez` | `has_parking` / `is_ulez_zone` |
| `latitud` / `longitud` | `latitude` / `longitude` |
| `iva_valor` / `iva_incluido` | `tax_rate` / `tax_included` |
| `id_fixner` | `legacy_erp_id` |
| `integracion_relacion_id` | `integration_external_id` |
| notes + alerts | `notes` / `notes_alert` / `internal_notes` / `internal_notes_alert` |
| `nora_franjas_horario` | `voicebot_time_slots` |

Skipped: `workplaces.featured` / `uuid`, dropped IVA-delegation cols.

Unique `(company_id, code)` among live rows when `code` is not null.

Do **not** create a nested `workplaces` table. A “zone inside a store” is **not** old workplaces and is out of scope.

## Establishment types: `tipos_establecimiento` → `establishment_types`

Config catalog (`/config/establishment-types`). Import before establishments.

| Old | New |
|---|---|
| `nombre` | `name` |
| (enum key) | `code` (`store` / `eci` / `cc`) |
| `dias_atraso_prl` | `health_and_safety_delay_days` |

## Delegations: `delegaciones` → `delegations`

Config catalog only (`/config/delegations`). Create **before** establishments because of `establishments.delegation_id`.

| Old | New |
|---|---|
| `nombre` | `name` |
| `nif` | `tax_id` |
| `empresa_id` | `company_id` (remap `empresas` → `companies` by NIF) |
| `direccion` | `address` |
| `moneda_id` | `currency_id` |
| `pais_id` | `country_id` |
| `serie_id` | `series_id` |
| `iva_incluido_coste` | `cost_includes_vat` |
| `recupera_iva` | `recovers_vat` |
| `informacion_facturacion` | `billing_info` (json) |

Deferred / dead: `impuesto`, `serie_factura_venta`, `serie_factura_compra`, `id_partner`, `banco_id` (OLD `bancos` = bank accounts, not NEW `banks`).

## Brands

`marcas.corporation_company_id` → `brands.corporation_company_id` (nullable FK `companies`, `nullOnDelete`). Brands stay global; no `company_id` tenant scope.

Customer commercial brand lives on **`company_relationships.brand_id`** when `kind = customer`, not on the party.

## SSO tenants: `tenants` + IdP tables

SSO customer orgs and IdP credentials (not the same as `owner_company_id` commercial tenancy).

| Old | New |
|---|---|
| `tenants` | `tenants` (`name` unique slug, e.g. `hermes`) |
| `proveedores_sso` | `sso_providers` (`nombre` → `name`, `slug`, `driver`) |
| `tenant_proveedores_sso_configuraciones` | `tenant_sso_provider_settings` (`proveedor_sso_id` → `sso_provider_id`, `config_key` → `key`, `config_value` → `value`) |
| `users_identificadores_sso` | `user_sso_identities` (`identificador` → `external_id`, `proveedor_sso_id` → `sso_provider_id`) |
| `users.tenant_id` | `users.tenant_id` (nullable FK `tenants`) |
| `users.login_unicamente_sso` | `users.sso_only` (already on users) |

Azure required setting keys stay `client_id`, `client_secret`, `tenant_id` (IdP directory id inside `value`). Values may be encrypted at rest in OLD — decrypt/re-encrypt on import as needed.

Skipped: one-off data migration that bulk-assigned Hermes users by collaborator graph (import logic, not schema).

## Deferred (not data-loss)

These FM / V2 columns have **no table yet**. Keep them in the mapping JSON as `old_only` / deferred notes:

- `operarios` (site people)
- FM collaborator ACL
- Dual-write `cliente_id` / `proveedor_id` / `tecnico_id` bridges
- `peticion_id` (onboarding requests catalog not in NEW)
- `tecnicos.estado_id` stored as `company_relationships.legacy_status_id` until a statuses catalog exists
- Remaining V2 relationship accounting: receivable/payable accounts, tax-rate FKs, credit limits, payment terms
- `clientes.uuid` (NEW uses bigint ids)

## Seed in laravel_optima

Operating companies from `PartyCompanyResolver::EMPRESA_NIFS`:

| Alias | Tax ID | Kind |
|---|---|---|
| OR | `B66409087` | `operating_company` |
| ORIL | `IE4115369FH` | `operating_company` |

Attach `admin@optima.test` via `company_user`.
