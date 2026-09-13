# 16 — Technician requests (peticiones)

**Status:** schema + CRUD created in v2. **No production data imported.**

## What it is in Optima Prod

Technician service requests (`peticiones`) plus screening / filtraje child records. Priorities come from `tecnicos_prioridades`; statuses from polymorphic `estados` (request vs screening kinds). Service types link via `peticiones_tipos_servicios`; technicians via `peticion_tecnico`.

## Mapping

| Legacy | v2 |
| --- | --- |
| `peticiones` | `technician_requests` |
| `filtraje` | `is_screening` |
| `peticion_id` | `parent_technician_request_id` |
| `estados` (request/screening) | `technician_request_statuses` (`kind` = `request` \| `screening`) |
| `tecnicos_prioridades` | `technician_request_priorities` (`key` = urgent/high/medium/low) |
| `peticiones_tipos_servicios` | `technician_request_service_type` |
| `peticion_tecnico` | `technician_request_technician` → `company_relationships` (technician) |
| `ot_id` | `work_order_id` |
| `codigo` / `descripcion` / `observaciones` / `observaciones_privada` | `code` / `description` / `notes` / `internal_notes` |
| `poblacion` / `codigo_postal` / `direccion_1` / `provincia` | `city` / `postal_code` / `address_line` / `province_name` |
| `pais_id` / `idioma_id` | `country_id` / `language_id` |
| `solicitado_por_id` / `responsable_tec_id` | `requester_user_id` / `responsible_user_id` |
| `fecha_resolucion` / `fecha_limite_resolucion` / `fecha_proxima_accion` | `resolved_at` / `due_at` / `next_action_at` |
| `prioridad_id` / `estado_id` | `technician_request_priority_id` / `technician_request_status_id` |

**Company scope:** `company_id` = active operating company (required), same pattern as incidents.

## Status seed (legacy IDs)

| ID | Kind | Name | Open |
| --- | --- | --- | --- |
| 38 | request | Abierta | yes |
| 39 | request | En progreso | yes |
| 40 | request | Finalizada | no |
| 48 | request | Cancelada | no |
| 59 | screening | Abierto | yes |
| 54 | screening | Cita programada | yes |
| 55 | screening | Docu pendiente | yes |
| 56 | screening | Revision manager | yes |
| 57 | screening | No apto | no |
| 58 | screening | Apto | no |

## Priority seed (legacy IDs 1–4)

Due dates are **computed in `TechnicianRequestService`** from `key` (not stored as hours):

| ID | Name | Key | Due |
| --- | --- | --- | --- |
| 1 | Urgencia | urgent | +3 hours |
| 2 | Prioridad alta | high | +3 weekdays |
| 3 | Prioridad media | medium | +7 weekdays |
| 4 | Prioridad baja | low | +14 weekdays |

## Service behaviours

- Create: requester = auth user; default status 38 (request) or 59 (screening); allocate `code` via numbering when pattern active; sync service types.
- Update: sync service types; set/clear `resolved_at` when entering/leaving closed statuses (40, 48, 57, 58).
- Cancel: force status 48 for non-screening open requests.
- Create screening: clone parent fields, `is_screening=true`, status 59, new code, clear resolved/next_action.
- Attach technician: must be technician `company_relationship` under owner; if status is 38/39 → finalize to 40 + `resolved_at`.

## Deferred

Chat, mails, `direccion_2`, auto-create Tecnico party on Apto, OT modal assign flow, reports/Excel.

## UI

- Main: `/technician-requests`
- Config statuses: `/config/technician-request-statuses`
- Config priorities: `/config/technician-request-priorities`
- Numbering resource: `technician_requests`
- Saved filters page key: `technician_requests`
