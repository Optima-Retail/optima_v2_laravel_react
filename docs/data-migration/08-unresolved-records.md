# 08 — Unresolved records and decisions

Nothing in this file is an import instruction. Each item is **UNRESOLVED — REQUIRES REVIEW**.

This phase did **not** query production, so there are no concrete “cliente id 1847” rows. After a dry-run, append **instance** sections with legacy PKs.

---

## unknown mapping

| Item | Problem | Required action |
| --- | --- | --- |
| CRM `companies` / `contacts` / `deals` / `workplaces` | Different domain from v2 `companies` | Do not import into operational companies |
| `marcas.corporation_company_id` | May FK legacy CRM companies | Confirm target table on live schema |
| `clientes`/`proveedores`/`establecimientos`.`provincia` | Not clearly `provinces.id` | Dump column type; write a province name→id map or leave NULL |
| `users.username`, `users.tenant_id` | Column vs v2 tenants | Schema dump |
| `users.establecimiento_id`, `clientes_users`, `establecimientos_users` | No v2 table | Product: drop vs new portal model |
| `marcas_usuarios` vs `brand_collaborators` vs `company_user` | Three different ideas | Decide |
| `tecnicos.codigo` vs `codigo_tecnico` | Two codes, two v2 fields | Which maps to `owner_reference` / `external_code` |
| `partner` relationship kind | No legacy table | Ignore until a source exists |
| `company.kind` corporation/holding/ute | No confirmed source | Manual |
| Incident origin modelos other than 5/6/7 | v2 origin_type closed set | Drop origin or extend v2 |
| `contratos_tipos` | v2 contracts have no type column | Drop or add column |
| `Cliente::usuarios()` pivot name vs `clientes_users` | Possible mismatch | Inspect live pivots |
| Live vs dump: `incidencias.establecimiento_id`, `tecnicos.activo`, `contratos.ots_tipo_id` | Models stale vs 2024 drops | Re-dump |
| Password hash algorithm | Must match Laravel hasher | Sample hashes |
| App timezone | Date copy | Compare `config('app.timezone')` |
| Soft-deleted legacy rows | Import or skip | Policy |
| Extra `empresas` beyond OR/ORIL | Operating companies | List live `empresas` |
| Which operating company owns a given cliente | Brand? Delegation.empresa_id? Always OR? | **Blocks relationship import** |
| `proveedores_clientes` / cliente–técnico links | No v2 link table | Confirm whether unused |

---

## conflicting identity

### Same NIF on multiple party tables

**Problem:** v2 `tax_id` unique; legacy NIF not unique. A cliente and proveedor may share `B12345678`.

**Status:** unresolved  
**Required action:** export collisions (source, id, nif, name). Human chooses merge (one company, two relationship kinds) **or** keep separate with a reviewed tax_id change.  
**Do not** auto-merge.

### Same NIF inside one table

Two `clientes` with the same nif.  
**Status:** unresolved  
**Action:** quarantine both.

### Same brand name

`brands.name` unique.  
**Action:** rename review.

### Same user email

**Action:** quarantine; do not invent plus-aliases.

---

## missing FK

Classify at dry-run:

- Establishments whose `cliente_id` is missing/deleted
- Clients without `marca_id` (legacy treats as required)
- Technician incidents without `tecnico_id` (v2 technician_id nullable — still log)
- Collaborators whose `relacion_id` or `user_id` is gone
- Contracts without client
- Evaluations without establishment (v2 **required**)

---

## invalid data

- `tax_id` longer than 32 after trim
- `phone` longer than 50
- Invalid emails vs v2 `email` rule
- Invalid UUID `id_publico` (replace with generated UUID — allowed — but log)
- `0000-00-00` dates
- Decimals overflowing 10,2
- Grouping enum values outside `cliente|establecimiento|ot`
- `person_type` not `F`/`J`
- Empty string on unique columns (`tax_id`, `code`, `slug`)

---

## missing required field

- Party with empty `razon_social` and `nombre_comercial`
- User without email
- Evaluation without establishment
- Technician incident without type
- Article language without name

---

## ambiguous transformation

| Topic | Why |
| --- | --- |
| `clientes.estado` → `status` enum | Boolean vs 5 strings |
| `proveedores.activo` → `status` | Same |
| `tecnicos.estado_id` → `status` | Only `legacy_status_id` is safe |
| Email lowercase | Not in v2 rules |
| NIF checksum / hyphen strip | Not in v2 `CompanyValidation` |
| `responsable_id` on technician → `account_owner_id` vs `quality_owner_id` | Best-fit only |
| `companies.brand_id` vs relationship `brand_id` | Dual columns |
| Membership: all `empleado_optima` on every operating company vs per delegation vs per brand | No code equivalent |
| `is_intercompany` vs `classification` | Partial |
| Incident `establishment_id` when origin is company/brand without evaluation | May be NULL; lists hide row |

---

## deprecated data (intentionally not migrated)

| Legacy | Reason |
| --- | --- |
| OT / invoice / estimate / ticket / stock / treasury tables | No v2 tables |
| Technician incident invoice counters / pivots | Documented dead in v2 migration |
| Incident fecha_limite / fecha_recibida / fecha_ultima_revision | Documented dead |
| Contract coste / ots_tipo_id / fechas | Dropped |
| Article nombre/precio on `articulos` | Split to language/client tables |
| `marcas.minutos_reunion` | No column |
| `tecnicos.activo` | Column dropped |
| Most `colaboradores` modelo_ids | No pivot |
| FileMaker leftovers not on `legacy_erp_id` | No column |

```text
Legacy field
    ↓
No direct target
    ↓
Possible reason (domain not in v2 / dropped / moved)
    ↓
Data preservation decision: ignored | requires manual decision | unresolved
```

---

## manual migration required

1. **Operating-company owner** for each customer/supplier/technician relationship.
2. **NIF collision** resolution.
3. **`company_user` population**.
4. **Client-portal users**.
5. **Status enum** for relationships.
6. **Province** mapping.
7. **Catalog ID** checksum vs seeders (especially skipped ids 15 / 3 / 2).
8. **Wipe vs keep** v2 seed demo users and OR/ORIL before prod-like import.
9. Whether to import **soft-deleted** legacy rows.
10. CRM overlay vs operational companies.

---

## Instance log (fill during dry-run)

### Template

```markdown
## Legacy `{table}` ID {id}

Problem:
* …

Status: unresolved

Required action: manual review
```

Do not silently choose a mapping for any instance.
