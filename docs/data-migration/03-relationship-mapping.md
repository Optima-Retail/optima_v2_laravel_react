# 03 — Relationship mapping

Every new FK is resolved through a **mapping table** unless it is a catalog ID that seeders preserve **and** production has been verified.

---

## Cardinality and FK changes

| Legacy relationship | New relationship | Type | FK changes | Transformation | Notes |
| --- | --- | --- | --- | --- | --- |
| `clientes.marca_id` → `marcas` (required) | `company_relationships.brand_id` → `brands` (nullable) | 1:N preserved, moved | `marca_id` → `brand_id` via `migration_brand_map` | Brand lives on **customer relationship**, not required by schema | High |
| `clientes` 1:N `establecimientos` | party `companies` 1:N `establishments` | preserved | `cliente_id` → `establishments.company_id` via `migration_company_map` source=`clientes` | **Not** the operating company | High |
| `establecimientos.cliente_facturacion_id` → `clientes` | `establishments.billing_company_id` → `companies` | preserved | billing client → party company | May differ from site owner | High |
| `clientes` N:1 `delegaciones` | `company_relationships.delegation_id` | moved | same idea, new parent | | High |
| `users` standalone (no company) | `company_user` M:N + `users.active_company_id` | **new** | no legacy FK | Membership rule **UNRESOLVED** | Unresolved |
| `clientes_users` M:N | *(none)* | removed | — | Do not copy into `company_user` | Unresolved |
| `establecimientos_users` M:N | *(none)* | removed | — | Client-portal | Unresolved |
| `marcas_usuarios` M:N | `brand_collaborators`? / `company_user`? | unresolved | — | Different from `colaboradores` | Unresolved |
| `users.establecimiento_id` → site | *(none)* | removed | — | | Unresolved |
| `users.manager_id` / `team_leader_id` | same | preserved | remap user IDs | Two-pass import | High |
| `users.equipo_id` | `users.team_id` | rename | team map | | High |
| `colaboradores` polymorphic | typed `*_collaborators` | split | `modelo_id` + `relacion_id` + `user_id` | See modelo table below | High for listed modelos |
| `incidencias` origin morph (`origen_modelo_id`) | `origin_type` string + `origin_id` | transform | integer modelo → `establishment` / `company` / `brand` | Other modelos dropped | Medium |
| `incidencias` related morph | `related_type`=`evaluation` + `related_id` | transform | only modelo 21 | | High |
| `incidencias` → establecimiento (dropped column) | `incidents.establishment_id` | computed | from origin or evaluation | | High |
| `tecnicos_incidencias.tecnico_id` → `tecnicos` | `technician_incidents.technician_id` → `company_relationships` | **FK target change** | map tecnico → **relationship** id | Copying company id is wrong | High |
| `vehiculos.tecnico_id` | `vehicles.company_relationship_id` | same change | technician relationship | | High |
| `articulo_cliente.cliente_id` | `article_clients.company_relationship_id` | **FK target change** | customer **relationship** id | Not `companies.id` | High |
| `contratos.cliente_id` | `contracts.company_id` | FK target change | party **company** id | Opposite of article_clients | High |
| `contratos` M:N `establecimientos` | `contract_establishment` | preserved | both IDs remapped | Unique pair | High |
| `evaluaciones.establecimiento_id` | `evaluations.establishment_id` | preserved | establishment map | Required | High |
| `tecnicos` 1:N `operarios` | *(no v2 table)* | removed | — | Out of scope | High |
| `clientes` ↔ `tecnicos` (if a pivot exists) | *(no `company_relationship_links` table in v2)* | unresolved | — | **UNRESOLVED — REQUIRES REVIEW** | Unresolved |
| `delegaciones.empresa_id` (string) | `delegations.company_id` (bigint) | transform | `migration_company_map` source=`empresas` | | High |
| `marcas.corporation_company_id` | `brands.corporation_company_id` | unresolved | may point at **CRM** `companies`, not v2 parties | Do not copy blindly | Unresolved |
| Self-FK `clientes.cliente_reportado_id` | `company_relationships.reported_customer_relationship_id` | transform | cliente → **relationship** id | Import customers twice or defer this FK | High |
| CHECK `owner_company_id <> related_company_id` | new | — | operating company must not equal party | Merge-by-NIF could violate this if owner tax_id reused | High |

---

## One-to-one / one-to-many / many-to-many

| Pattern | Legacy | v2 |
| --- | --- | --- |
| 1:N brand → clients | `marcas` hasMany `clientes` | brand has many **customer relationships** (`brand_id` on relationship) |
| 1:N client → stores | `cliente_id` | party company → establishments |
| M:N staff ↔ company | implicit | `company_user` (unique pair, soft delete **without** deleted_token) |
| M:N contract ↔ store | `contratos_establecimientos` | `contract_establishment` |
| Polymorphic collaborators | one table | six typed pivots |
| Polymorphic incident origin | modelo id | closed string set |
| Party roles | separate tables | one company + many relationship kinds |

---

## New relationships (no legacy equivalent)

| New | How to populate |
| --- | --- |
| `company_relationships` | One per migrated cliente/proveedor/tecnico against a chosen operating company |
| `company_user` | **UNRESOLVED** |
| `users.active_company_id` | After membership |
| `incidents.establishment_id` / `evaluation_id` | Derived |
| Typed collaborator pivots | From `colaboradores` filtered by `modelo_id` |

---

## Removed relationships (data not importable into v2)

- Work-order assignments (`ot_tecnico`, OT → establishment, OT → client)
- Invoice / estimate graphs
- Technician incident ↔ purchase invoice
- CRM deals / contacts / workplaces
- `operarios` under technicians
- Client and establishment **user** pivots
- Most `colaboradores` modelo types (OT=2, presupuesto=1, factura, ticket, …)

---

## Polymorphic `colaboradores` → typed pivots

Legacy: `(user_id, relacion_id, modelo_id)` from `ModeloEnum`.

| modelo_id | Legacy parent | New parent FK | Resolve `relacion_id` via |
| ---: | --- | --- | --- |
| 5 | Marca | `brand_collaborators.brand_id` | `migration_brand_map` |
| 6 | Cliente | `company_relationship_collaborators.company_relationship_id` | cliente → **customer relationship** |
| 3 | Tecnico | same pivot | tecnico → **technician relationship** |
| 4 | Proveedor | same pivot | proveedor → **supplier relationship** |
| 7 | Establecimiento | `establishment_collaborators.establishment_id` | `migration_establishment_map` |
| 22 | Incidencia | `incident_collaborators.incident_id` | `migration_incident_map` |
| 15 | TecnicoIncidencia | `technician_incident_collaborators.technician_incident_id` | `migration_technician_incident_map` |
| 21 | Evaluacion | `evaluation_collaborators.evaluation_id` | `migration_evaluation_map` |

`user_id` always via `migration_user_map`. Duplicate `(parent, user)` unique keys: keep first, log rest.

---

## Incident origin resolution

v2 allows `origin_type` ∈ {`establishment`, `company`, `brand`}.

| Legacy `origen_modelo_id` | `origin_type` | `origin_id` from |
| ---: | --- | --- |
| 7 Establecimiento | `establishment` | `migration_establishment_map` |
| 6 Cliente | `company` | `migration_company_map` source=`clientes` (**party** company) |
| 5 Marca | `brand` | `migration_brand_map` |
| other | **UNRESOLVED** | do not import origin; log in 08 |

Then set `establishment_id`:

1. If origin is establishment → that id.
2. Else if related is evaluation → evaluation’s establishment.
3. Else null (list scoping may hide the row).

`related_type` only `evaluation` (modelo 21).

---

## How each new FK is resolved (checklist)

| New FK | Resolve |
| --- | --- |
| `companies.country_id` / `language_id` / `province_id` | Catalog map or seeder IDs after verification |
| `company_relationships.owner_company_id` | Operating company (empresas / seeder) |
| `company_relationships.related_company_id` | Party map for that source table |
| `company_relationships.brand_id` | `migration_brand_map` |
| `*_user_id` on relationships, sites, incidents | `migration_user_map` then **membership** |
| `establishments.company_id` | clientes → party company |
| `establishments.billing_company_id` | clientes (facturación) → party company |
| `technician_incidents.technician_id` | tecnicos → **relationship** |
| `vehicles.company_relationship_id` | tecnicos → relationship |
| `article_clients.company_relationship_id` | clientes → **customer relationship** |
| `contracts.company_id` | clientes → **party company** |
| `incidents.incident_status_id` etc. | copy ID if catalog verified |
| `delegations.company_id` | empresas string → operating company |

**Never** copy a legacy integer into a v2 FK that changed target table.
