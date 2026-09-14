# 18 — Technician rates, ratings & establishment technician lists

**Status:** schema + CRUD created in v2. **No production data imported.**

## Mapping

| Legacy | v2 |
| --- | --- |
| `tecnicos_tarifas` | `technician_rates` |
| `valoraciones_tecnico` | `technician_ratings` |
| `establecimiento_tecnico` | `establishment_technician_blacklist` |
| `establecimiento_tecnico_fav` | `establishment_favorite_technicians` |

## Technician rates (`tecnicos_tarifas`)

One rate card per technician `company_relationship` (`company_relationship_id` unique).

| Legacy | v2 |
| --- | --- |
| `tecnico_id` | `company_relationship_id` (kind=`technician`) |
| `hora_*` | `labor_*_amount` (weekday / night / weekend / holiday / urgent) |
| `desplazamiento_*` | `travel_*_amount` (same windows) |

UI: technician edit → **Rates** tab (`TechnicianRatesPanel`).  
API: `GET/PUT /technicians/{relationship}/rates` (authz via `company_relationships.view|update`).

## Technician ratings (`valoraciones_tecnico`)

| Legacy | v2 |
| --- | --- |
| `tecnico_id` | `company_relationship_id` |
| `ot_id` | `work_order_id` (nullable, nullOnDelete) |
| `nota` | `score` (0–10) |
| `observaciones` | `notes` |
| `origen_id` → `origenes` | `source` enum string: `optima` \| `customer` (no `origenes` table) |

After create/update, `TechnicianRatingService` recomputes on the relationship:

- `optima_score` / `optima_score_count`
- `customer_score` / `customer_score_count`
- `average_score` (avg of all ratings)

UI: technician **Metrics** tab → ratings list + add form.  
API: `GET/POST /technicians/{relationship}/ratings`.

## Establishment technician lists

| Legacy | v2 pivot | Form field |
| --- | --- | --- |
| `establecimiento_tecnico` | `establishment_technician_blacklist` | `blocked_technician_ids` |
| `establecimiento_tecnico_fav` | `establishment_favorite_technicians` | `favorite_technician_ids` |

Both pivots: `(establishment_id, company_relationship_id)` unique, `withTimestamps()`. Synced on establishment create/update like collaborators. Options come from `EstablishmentService::technicianOptions()` (owner technician relationships).

UI: establishment form → **Technicians** tab (two MultiSelects).

## Deferred / skipped (related)

| Legacy | Status |
| --- | --- |
| `tecnico_incidencia_factura_compra` | **Deferred** — purchase-invoice / billing link; document only until billing domain exists (see inventory skipped `facturas_*`) |
| Already imported elsewhere | `tecnicos_prioridades`, `tecnicos_incidencias_mensajes`, `peticion_tecnico` — do not recreate |
