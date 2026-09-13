# 12 — Saved filters (`filtros`)

**Status:** schema + API + React wired for **main-sidebar lists only**. Config catalog indexes do **not** persist filters. **No production data imported.**

## What it is in Optima Prod

`filtros` stores named filter presets per user and document class (`modelo_id`):

| Column | Meaning |
| --- | --- |
| `nombre` | Preset name |
| `user_id` | Owner |
| `modelo_id` | Morph catalog row (OT, client, …) |
| `json_filtro` | JSON bag of active filters |
| `por_defecto` | One default per user+modelo |

API: list / upsert by name / delete / mark default.

## v2 mapping

| Legacy | v2 | Notes |
| --- | --- | --- |
| `filtros` | `saved_filters` | Rename |
| `nombre` | `name` | |
| `user_id` | `user_id` | |
| `modelo_id` | `page_key` | **No morph / no `modelos` table** — string resource key |
| `json_filtro` | `filters` (JSON column) | Scalar string map |
| `por_defecto` | `is_default` | Only one default per user+page_key |

### Allowed `page_key` values

`companies`, `clients`, `suppliers`, `technicians`, `establishments`, `contracts`, `estimates`, `work_orders`, `evaluations`, `compliments`, `incidents`, `brands`, `technician_incidents`

Config type/status catalogs are **excluded** (search-only, no save UI).

## Backend

- Model: `App\Models\SavedFilter`
- Service: `App\Domain\SavedFilters\Services\SavedFilterService` (upsert by name, clear other defaults)
- Routes (auth):
  - `GET /saved-filters?page_key=`
  - `POST /saved-filters`
  - `DELETE /saved-filters/{id}`
  - `PATCH /saved-filters/{id}/default`

Ownership: only the authenticated user’s rows (no Spatie permission).

## React

- `FilterBar` supports `date` fields
- `SavedFiltersMenu` — save / apply / delete / set default; applies default on mount when URL filters are empty
- `RemoteDataTable` prop `savedFiltersPageKey` enables the menu on main lists

## Main list filter expansions (beyond search)

Each main Index adds useful selects/dates (status, is_active, created_from/to, …) honored by the corresponding service `paginate*` methods.

## Historical import

Remap `modelo_id` → `page_key` via a fixed catalog map. Remap `user_id` through the user ID map. Do not import presets whose modelo is not in the allowed page keys.
