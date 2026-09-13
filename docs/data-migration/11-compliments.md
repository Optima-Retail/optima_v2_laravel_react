# 11 — Compliments (felicitaciones)

**Status:** schema + CRUD created in v2. **No production data imported.**

## What it is in Optima Prod

QC compliments from brands / clients / establishments. Staff attach users + a display score; `QcoinsProcessor` credits action **Felicitacion (11)** using closed-WO weights since the last compliment credit.

### Live Prod columns (after 2024_02_13 correction)

| Column | Meaning |
| --- | --- |
| `modelo_id` + `relacion_id` | Morph subject: Marca (5) / Cliente (6) / Establecimiento (7) |
| `tipo_felicitacion_id` | Catalog (Rapidez / Amabilidad / Gestión) |
| `comentario` | Free text |
| `felicitacion_usuario.puntuacion` | Display score on the user pivot |

**Dead columns already dropped in Prod:** `asunto`, `usuario_id`, `establecimiento_id`, header `puntuacion`.

## Related tables decision

| Legacy | v2 | Decision |
| --- | --- | --- |
| `tipos_felicitacion` | `compliment_types` | Import catalog (ID-preserving seeder 1–3) |
| `felicitaciones` | `compliments` | Transform — typed subject FKs, no morph |
| `felicitacion_usuario` | `compliment_user` | Rename pivot (`score`) |
| `archivos` (modelo felicitacion) | `compliment_attachments` | Typed attachments table + upload UI on create/edit |
| `users` | `users` | Already exists |
| `marcas` / `clientes` / `establecimientos` | `brands` / `company_relationships` / `establishments` | Already exist |
| `accion_usuarios` | `user_action_scores` | Already exists — `document_type=compliment` |
| `modelos` | — | **Ignore** (morph catalog) |
| `filtros` | `saved_filters` | Main-list presets only — see [12-saved-filters.md](12-saved-filters.md) |
| `vista_felicitaciones` | — | **Ignore** (SQL view → Eloquent list) |
| `kpis_diarios` | — | **Defer** (dashboard KPI snapshots, not CRUD) |
| `establecimiento_tipo_felicitacion` / `usuario_tipo_felicitacion` | — | **Ignore** (no migrations / unused) |

## Subject morph rewrite

| Legacy `modelo_id` | v2 |
| --- | --- |
| 5 Marca | `subject_type=brand` + `brand_id` |
| 6 Cliente | `subject_type=customer` + `company_relationship_id` (customer relationship of operating company) |
| 7 Establecimiento | `subject_type=establishment` + `establishment_id` |

Importer must remap `relacion_id` through brand / company-relationship / establishment ID maps. Cliente rows become relationships, not party company IDs.

## Write path

1. Create `compliments` + attach `compliment_user` rows.
2. `QualityScoreProcessor::processCompliment` writes `user_action_scores` and credits via `User::addQualityScore` (action 11).

## UI

- Main sidebar: `/compliments`
- Config types tab: `/config/compliment-types`
- Create: optional file field (stored after create when permitted)
- Edit: attachments panel (upload / download / delete), same pattern as contracts/work orders

## Attachments

Permissions (discovered from `ComplimentPolicy`):

- `compliments.view-attachments`
- `compliments.upload-attachments`
- `compliments.download-attachments`
- `compliments.delete-attachments`

Storage disk: `local` under `compliments/{id}/attachments/{Y-m}/`.
