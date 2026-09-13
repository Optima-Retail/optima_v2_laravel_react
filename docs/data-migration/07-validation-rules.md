# 07 — Validation rules

Run on a copy, never on production. Counts below are **methods**, not measured production numbers (this phase did not query live DBs).

---

## Count validation

| Check | Expectation |
| --- | --- |
| `count(companies where kind=party)` vs `count(clientes)+count(proveedores)+count(tecnicos)` minus quarantined | Equal **only if** no merge and every source row imported |
| `count(company_relationships kind=customer)` vs imported clientes | 1:1 with `migration_company_relationship_map` source=clientes |
| Same for supplier / technician | 1:1 |
| `count(establishments)` vs `establecimientos` imported | 1:1 minus unresolved codes |
| `count(users)` vs `users` imported | 1:1 minus email collisions |
| `count(brands)` vs `marcas` | 1:1 minus name collisions |
| `count(incidents)` vs `incidencias` | 1:1 minus rows that fail required FKs |
| Mapping table rows | Every imported PK has exactly one map row |

**Why counts differ (legitimate):** quarantined tax_id, skipped catalogs, out-of-scope tables, seeder OR/ORIL already present, soft-deleted source rows excluded by policy (**UNRESOLVED** whether to import `deleted_at` rows).

---

## FK validation

Every new FK must exist:

```text
companies.country_id → countries.id (or NULL)
company_relationships.owner_company_id → companies.id
company_relationships.related_company_id → companies.id
company_relationships.owner_company_id ≠ related_company_id
establishments.company_id → companies.id
technician_incidents.technician_id → company_relationships.id
  AND that row kind = technician
article_clients.company_relationship_id → company_relationships.id
  AND kind = customer
vehicles.company_relationship_id → kind = technician
contracts.company_id → companies.id (party)
evaluations.establishment_id → establishments.id
incidents.origin_id consistent with origin_type + map
all *_user_id → users.id
```

Orphans = rows whose FK is non-null but missing. **Do not** point them at a dummy company.

---

## Orphan / dangling legacy FKs

Before import, count:

- `establecimientos.cliente_id` not in `clientes`
- `clientes.marca_id` not in `marcas`
- `tecnicos_incidencias.tecnico_id` not in `tecnicos`
- `incidencias` origin ids not in the target table
- `colaboradores.user_id` missing users
- `contratos.cliente_id` missing

Those rows go to 08, not to v2.

---

## Uniqueness

| Constraint | Validation |
| --- | --- |
| `companies.slug` | no duplicates; none empty |
| `companies.tax_id` | no duplicates except NULL; no `''` |
| `company_relationships (owner, related, kind, deleted_token)` | one role per pair |
| `users.email` / `username` | unique |
| `brands.name` | unique |
| `establishments (company_id, code)` | unique; `code` not `''` |
| `articles.code` | unique |
| `evaluations.public_id` | UUID unique |
| `article_clients (article_id, company_relationship_id)` | unique |
| `company_user (company_id, user_id)` | unique including soft-deleted |
| collaborator pivots `(parent_id, user_id)` | unique |

---

## Data completeness (required v2 fields)

| Table | Required |
| --- | --- |
| companies | name, slug, kind |
| company_relationships | owner, related, kind, status, classification, deleted_token |
| users | name, email, password |
| brands | name |
| establishments | company_id, name, is_active |
| technician_incidents | technician_incident_type_id |
| evaluations | public_id, establishment_id |
| article_clients | sale_price |

Rows missing these after transform → 08.

---

## Transformation validation

- Grouping enum only `customer|establishment|work_order`
- `person_type` only `F`/`J`/NULL
- `origin_type` only `establishment|company|brand`/NULL
- `related_type` only `evaluation`/NULL
- Emails that fail `email` rule
- `tax_id` length ≤ 32
- `phone` ≤ 50
- `slug` ≤ 64
- `responded_at` is date-only
- Evaluation `tiempo_qc` interpreted as **minutes**
- Incident `tiempo` as **seconds**
- Decimals fit 10,2

---

## Business validation (from v2 app)

- Technician incidents are scoped to the **owner company** via the technician relationship (not via establishment). After import, a user with active company OR must see only relationships owned by OR.
- Establishment lists are scoped to **party companies** that have a customer relationship with the active operating company.
- Collaborator and owner user IDs should be members of `company_user` for that owner — otherwise UI selects hide them (DB still accepts the FK).
- `CompanyRelationship` unique kind: the same party can be customer **and** technician for the same owner (two rows). That is valid. Two customer rows for the same pair are not.

---

## Membership validation

Until the rule is decided, assert: **zero** `company_user` rows **or** only explicitly reviewed attachments. Do not attach all users to OR “to make the UI work”.

---

## Mapping integrity

- Every imported operational PK appears once in its map.
- No `new_id` in a 1:1 map used by two legacy ids (unless merge approved).
- `migration_company_relationship_map` `new_id` matches `related_company_id` + `kind` for that source.
