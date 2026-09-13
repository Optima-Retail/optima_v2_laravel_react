# 13 — Forms and form templates (formularios / plantillas)

**Status:** schema + CRUD created in v2 (scope B). **No production data imported.**

## What it is in Optima Prod

- **Plantilla** — reusable form structure (sections + fields), owned by brand / client / establishment / bible / global.
- **Formulario** — filled instance (usually from a template), linked to a work order or technician, with status workflow (`Creando` → `Editando` → `Revisando` → `Terminado`).

## Related tables decision

| Legacy | v2 | Decision |
| --- | --- | --- |
| `formularios_tipos` | `form_types` | Already seeded (IDs 1–6) |
| `formularios_estados` | `form_statuses` | Already seeded (IDs 1–4 + next) |
| `plataformas` | — | Skip table — `forms.app_platform_id` uses AppPlatform ints (1–3) |
| `idiomas` / `users` | `languages` / `users` | Already exist |
| `modelos` | — | **Ignore** (morph catalog) |
| `biblias` | `form_bibles` | Minimal catalog for template owner |
| `plantillas` | `form_templates` | Transform — bigint PK, typed owner, **scoped by `company_id`**, no morph |
| `secciones_plantillas` | `form_template_sections` | Transform |
| `campos_plantillas` | `form_template_fields` | Transform |
| `establecimiento_plantilla` | `establishment_form_template` | Rename pivot |
| `ots_tipos_plantillas` | `work_order_type_form_templates` | Transform — typed owner FKs |
| `formularios` | `forms` | Transform — typed subject FKs |
| `secciones_formularios` | `form_sections` | Transform |
| `campos_formularios` | `form_fields` | Transform |
| `filtros` | `saved_filters` | Page keys `forms` / `form_templates` |
| `vista_*` | — | **Ignore** |
| `permiso_accions` / `estados` | Spatie / domain statuses | **Ignore** |
| `preferencia_plantillas` / `ticket_plantillas` / `plantillas_outlook` | — | **Defer** |
| `visitas` / `planificacion_lineas` | — | **Defer** |
| `iteraciones` | `contract_iterations` | See [14-contract-iterations.md](14-contract-iterations.md) |
| `plantillas_repetibles` | — | **Ignore** |

## Company scope

`form_templates.company_id` ties each template to the active company. List/create/edit/delete and template pickers on forms only expose templates for that company.

## Morph rewrites

### Template owner

| Legacy `modelo_id` | v2 |
| --- | --- |
| 5 Marca | `owner_type=brand` + `brand_id` |
| 6 Cliente | `owner_type=customer` + `company_relationship_id` |
| 7 Establecimiento | `owner_type=establishment` + `establishment_id` |
| 31 Biblia | `owner_type=bible` + `form_bible_id` |
| null | `owner_type=global` |

### Form subject

| Legacy `modelo_id` | v2 |
| --- | --- |
| 2 OT | `subject_type=work_order` + `work_order_id` |
| 3 Tecnico | `subject_type=technician` + `company_relationship_id` |

## Skipped from this change

- PDF / `GenerarReporteInformeJob`
- OT status mutations, materials / hours billing from form fields
- WhatsApp opt-in from technician forms
- Full Livewire parity for every special field widget (pragmatic text/json editor)

## UI

- Main sidebar: `/forms`, `/form-templates`
- Config: existing form types / statuses; `/config/form-bibles`
