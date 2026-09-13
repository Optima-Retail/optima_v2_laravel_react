# 05 — ID mapping strategy

## Never assume `legacy_id == new_id`

Operational primary keys must be treated as **opaque**. Even when a table is named the same (`users`) or a catalog **intends** to keep IDs, the importer uses an explicit map.

**Exception (catalogs only):** v2 seeders insert statuses/types with **legacy numeric IDs**. After a production checksum, those FKs may be copied. Until then, still write `migration_*_map` rows (legacy_id → same id) so the rule stays uniform.

---

## Why maps are required

1. Multiple legacy tables become one `companies` table (`clientes`, `proveedores`, `tecnicos`, `empresas`).
2. `empresas.id` is a **string**; `companies.id` is bigint.
3. Some FKs move from party id → **relationship** id (`tecnico_id`, `articulo_cliente.cliente_id`).
4. Auto-increment sequences in v2 already contain seeded OR/ORIL and demo users — collisions if IDs were copied.
5. Merge/split (if later approved) means many-to-one or one-to-many.

---

## Proposed mapping tables (spec only — do not create in production yet)

Suggested physical names. Implementation can use a dedicated `migration` connection. Columns below are logical.

### `migration_company_map`

| Column | Meaning |
| --- | --- |
| `legacy_source` | `clientes` \| `proveedores` \| `tecnicos` \| `empresas` |
| `legacy_id` | string (empresas) or decimal string of int |
| `new_id` | `companies.id` |
| uniqueness | unique `(legacy_source, legacy_id)` |
| many→one | **forbidden** unless a reviewed merge policy is added |
| one→many | no |

Example (illustrative, not real data):

| Legacy source | Legacy ID | New ID |
| --- | ---: | ---: |
| clientes | 123 | 847 |
| proveedores | 55 | 848 |
| tecnicos | 9 | 849 |
| empresas | OR | 1 |

If two NIFs were later merged, **both** source rows would point at one `new_id`. That is **not** authorized today.

### `migration_company_relationship_map`

| Column | Meaning |
| --- | --- |
| `legacy_source` | `clientes` \| `proveedores` \| `tecnicos` |
| `legacy_id` | source PK |
| `new_id` | `company_relationships.id` |
| uniqueness | unique `(legacy_source, legacy_id)` |
| notes | Required because vehicles, technician incidents, article_clients, collaborators, reported customer FK all need **relationship** ids |

### `migration_user_map`

| Column | Meaning |
| --- | --- |
| `legacy_source` | `users` |
| `legacy_id` | int |
| `new_id` | `users.id` |
| uniqueness | 1:1 |
| notes | Self-FKs `manager_id` / `team_leader_id` need a second pass |

### `migration_brand_map`

1:1 `marcas.id` → `brands.id`. Duplicate brand names may skip a row (08).

### `migration_establishment_map`

1:1 `establecimientos.id` → `establishments.id`.

### `migration_delegation_map`

1:1 `delegaciones.id` → `delegations.id`. Seeder already has 1 and 61 — **do not** insert duplicates; map legacy 1→existing 1 only after verifying they are the same office.

### Other operational maps (when those tables are imported)

| Table | Legacy | New | Cardinality |
| --- | --- | --- | --- |
| `migration_incident_map` | incidencias | incidents | 1:1 |
| `migration_technician_incident_map` | tecnicos_incidencias | technician_incidents | 1:1 |
| `migration_contract_map` | contratos | contracts | 1:1 |
| `migration_evaluation_map` | evaluaciones | evaluations | 1:1 |
| `migration_article_map` | articulos | articles | 1:1 |
| `migration_vehicle_map` | vehiculos | vehicles | 1:1 |

### Catalog maps (optional if IDs preserved)

`migration_country_map`, `migration_province_map`, `migration_language_map`, `migration_timezone_map`, `migration_team_map`, `migration_series_map`, `migration_integration_map`, `migration_incident_status_map`, …

If production ID ≠ seeder ID, these maps are **mandatory**.

---

## Identity resolution (parties)

### Question

Are `clientes`, `proveedores`, and `tecnicos` the same real-world company when NIF/name match?

### Evidence

| Signal | Finding |
| --- | --- |
| Unique NIF | **No** unique index on legacy NIF |
| Unique tax_id v2 | **Yes** (non-deleted) |
| Shared table | **No** — three tables |
| Application merge | **No** code path found that treats them as one entity |
| CRM 2026 `companies` | Separate overlay; not this mapping |

### Rule for this spec

**Treat each source row as its own `companies` party** plus one `company_relationships` row.

If the same NIF would violate `companies.tax_id` unique:

1. Do **not** auto-merge.
2. Write both candidates to [08-unresolved-records.md](08-unresolved-records.md) / a quarantine table.
3. A human chooses: merge (update both maps to one `new_id` + two relationships of different `kind`) **or** keep separate and alter tax_id (suffix is **not** authorized without review).

Matching on name/address/email/phone alone is **insufficient**.

### Operating companies

Seeded:

| tax_id | slug | name |
| --- | --- | --- |
| B66409087 | or | Optima Retail |
| IE4115369FH | oril | Optima Retail International Limited |

Map `empresas` rows to these **only** when tax_id/name match is confirmed. Extra `empresas` rows: new `operating_company` or ignore — **UNRESOLVED**.

---

## Lookup pattern for implementers

```text
legacy tecnicos.id = 50
    → migration_company_map (tecnicos, 50) → companies.id
    → migration_company_relationship_map (tecnicos, 50) → company_relationships.id
    → technician_incidents.technician_id uses the RELATIONSHIP id
```

```text
legacy articulo_cliente.cliente_id = 123
    → migration_company_relationship_map (clientes, 123) → article_clients.company_relationship_id
```

```text
legacy contratos.cliente_id = 123
    → migration_company_map (clientes, 123) → contracts.company_id
```

Same legacy client, **different** map tables depending on the FK.
