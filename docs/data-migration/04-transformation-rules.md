# 04 — Transformation rules

Only rules backed by schema, enums, or application code. Anything else is **UNRESOLVED — REQUIRES REVIEW**.

---

## Tax ID (`nif` → `companies.tax_id`)

**Legacy:** `nif` on `clientes`, `proveedores`, `tecnicos`. **Not unique.** Empty and duplicate values exist in principle.

**v2:** `string(32)` nullable, **unique** among non-deleted. Soft-delete suffixes tax_id (`Company::softDeleteSafely`). Validation: unique where `deleted_at` is null. No trim/uppercase/hyphen-strip in `CompanyValidation`.

**Allowed transform (minimal, to satisfy unique + NOT empty-string):**

1. Trim whitespace.
2. If result is `''`, store **NULL** (empty string would collide on unique).
3. If length > 32, **do not silently truncate** — log as unresolved (validation max 32).

**Not confirmed in code:** uppercase, remove hyphens/spaces, Spanish NIF checksum. `NifPersonTypeResolver` infers person type from pattern for **technicians**; it is not a write-time normalizer for `companies.tax_id`.

**Do not** merge two source rows because NIFs match. See [08-unresolved-records.md](08-unresolved-records.md).

---

## Person type

| Legacy | New | Meaning |
| --- | --- | --- |
| `PersonTypeEnum::Fisica` = `F` | `PersonType::Natural` = `F` | Natural person |
| `PersonTypeEnum::Juridica` = `J` | `PersonType::Legal` = `J` | Legal entity |

Confirmed same storage char. Source: `tecnicos.person_type`. Clients/suppliers: **UNRESOLVED** (column may be absent).

---

## Email

v2: `nullable`, `email`, `max:255`. No `strtolower` in `CompanyValidation`.

**Documented transform:** trim only. Lowercase is **not** required by current v2 rules. Duplicate emails on `users` still fail unique.

---

## Phone

v2: `string(50)` nullable. No E.164 / digit-only rule in company or user validation.

**Documented transform:** trim; if length > 50, unresolved (do not truncate).

---

## Slug (`companies.slug`)

Computed: `Company::uniqueSlugFromName($name)` → `Str::slug`, fallback `company`, suffix `-1`, `-2`, … Unique 64 chars.

Operating companies OR/ORIL: seeder uses `or` / `oril` — keep those if matching tax_id, do not regenerate.

---

## Work-order grouping enum

Legacy `clientes.agrupar_preventivos_por` / `agrupar_correctivos_por`:

| Legacy | New (`WorkOrderGroupingBasis`) | Meaning |
| --- | --- | --- |
| `cliente` | `customer` | Group by customer |
| `establecimiento` | `establishment` | Group by site |
| `ot` | `work_order` | Group by work order |

Any other value: unresolved.

---

## Relationship status

v2 `CompanyRelationshipStatus`: `prospect`, `active`, `blocked`, `inactive`, `archived`. Default `active`.

| Legacy | Storage | v2 mapping |
| --- | --- | --- |
| `clientes.estado` | boolean cast | **UNRESOLVED** (true→active and false→inactive is a guess — not in code) |
| `proveedores.activo` | boolean | **UNRESOLVED** |
| `tecnicos.estado_id` | FK to `estados` | Copy to `legacy_status_id` only. Mapping onto `status` **UNRESOLVED** |
| `tecnicos.activo` | dropped column | ignore |

Do **not** invent 1=active / 0=inactive for the five-value enum.

---

## Relationship classification

v2: `commercial` (default), `intercompany`, `public_administration`, `bank`, `other`.

Confirmed: `clientes.is_intercompany` true → `intercompany`. Otherwise leave default `commercial`. Other cases: no source.

---

## Relationship kind

| Source table | `company_relationships.kind` |
| --- | --- |
| clientes | `customer` |
| proveedores | `supplier` |
| tecnicos | `technician` |

`partner` has **no** confirmed legacy table.

Company `kind` for these parties: `party`. Operating orgs: `operating_company`. `corporation` / `holding` / `ute`: **UNRESOLVED** (CRM overlay / brand.corporation_company_id).

---

## Booleans

Legacy models cast many flags as boolean (`estado`, `prl`, `es_vip`, …). Typical MySQL `tinyint(1)`: `0`/`1`.

v2 booleans: `true`/`false` with defaults (often `false`).

| Legacy | Proposed | Confidence |
| --- | --- | --- |
| `1` / `true` | `true` | high |
| `0` / `false` | `false` | high |
| `NULL` on **required** v2 boolean | use column default | medium |
| `NULL` on nullable v2 boolean | NULL | high |
| strings `'si'`/`'no'` | **UNRESOLVED** (not seen on these fields) | — |

Alert flag name mismatch on Cliente (`observaciones_aviso` vs cast `observaciones_avisos`): confirm live column before mapping.

---

## Dates / datetimes / times

| Source | Target | Rule |
| --- | --- | --- |
| Laravel timestamps | timestamps | copy as UTC/app timezone — **UNRESOLVED** app timezone vs legacy `config('app.timezone')` |
| `tecnicos.fecha_alta` | `registered_at` timestamp | parse date; invalid → unresolved |
| `technician_incidents.responded_at` | **date** column | date-only; drop time |
| `due_at`, incident `control_at`/`closed_at` | datetime | copy |
| `hora_dia_inicio` / `hora_dia_fin` | `time` | copy |
| NULL | NULL | |
| `0000-00-00` | **UNRESOLVED** | do not coerce to NULL without review |

---

## Decimal / money

v2 uses `decimal(10,2)` for amounts and many scores (`total_amount`, `tax_rate`, `sale_price`, technician scores).

**No rounding policy in v2 domain code.** If legacy precision exceeds 10,2: unresolved (do not round silently).

Currency: not stored on companies. Delegations have `currency_id`. Incident durations: **seconds** (`incidents`) vs evaluation QC time **minutes**.

---

## Email / unique users

`users.email` unique. If two legacy users share an email (including soft-deleted): unresolved, do not invent `+1` aliases.

`username` unique nullable — confirm legacy column exists.

---

## Brand names

`brands.name` unique. Application **uppercases on store** (new app). Importer must apply the **same** uniqueness as the live unique index (typically case-sensitive in MySQL utf8mb4_unicode_ci = case-insensitive). Duplicate names: unresolved.

`minutos_reunion` has no target → deprecated.

---

## Establishment codes

Unique `(company_id, code)` with `code` nullable. Empty string → NULL. Two sites of the same client with the same code: unresolved.

---

## Evaluations `public_id`

UUID unique, required. If legacy `id_publico` is a valid UUID, copy it. Otherwise generate a new UUID (do not copy integer ids).

---

## Incident origin type

| Legacy modelo | String |
| --- | --- |
| 7 | `establishment` |
| 6 | `company` |
| 5 | `brand` |

`origin_id` remapped. Other modelos: drop origin, log.

---

## Grouping / article / vehicle FK target

Not a value transform: the **integer meaning** changes (`tecnicos.id` → `company_relationships.id`). See [03-relationship-mapping.md](03-relationship-mapping.md).

---

## Catalog IDs (exception)

These are **not** remapped if production matches seeders:

- Technician incident statuses: 96, 97, 98, 155, 156, 173
- Incident statuses: 89, 91, 92, 94, 150, 170
- Contract statuses: 77, 95, 78, 79, 80
- Evaluation statuses: 73–76, 99, 117, 133, 134, 139

Job title **15**, payment document **3**, estimate status **2**: skipped in seeders — exclude or map manually.

---

## Passwords

Copy `users.password` hashes without re-hashing. Confirm bcrypt/argon match. `must_change_password` / `sso_only` copied as booleans.

---

## Soft deletes

Copy `deleted_at` when both sides soft-delete. Unique keys with `deleted_token` (`company_relationships`) use `''` for live rows. `company_user` unique **ignores** deleted_token — a soft-deleted membership blocks a new pair.
