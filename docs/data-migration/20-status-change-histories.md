# 20 — Status change histories

**Status:** schema + runtime recording in v2. Production row migration from `historial_cambios_estados` is **not** implemented yet.

## Legacy

| Column | Role |
| --- | --- |
| `modelo_id` + `relacion_id` | Polymorphic document |
| `estado_antiguo_id` / `estado_nuevo_id` | Shared `estados` catalog |
| `user_id` | Actor |
| `justificacion` | Optional reason |
| `disminucion_precio_valor` | WO price-drop audit |

Observers / `ProcesaCambiosEstado` also write a **system chat line** (`user_id` SYS = 83). The chat UI “Histórico de cambios” tab shows those lines, not the audit table directly.

## v2

| Target | Notes |
| --- | --- |
| `status_change_histories` | `document_type` (`work_order`, `incident`, `evaluation`, `technician_request`) + `document_id`; status IDs are domain-catalog IDs |
| `*_chat_messages` (`type=system`) | Written by `StatusChangeHistoryService` via `DocumentChatService::postSystem` |

Templates: `lang/{en,es}/sistema.php` → `sistema.global.creado_con_estado` / `cambio_estado` / `cambio_estado_con_motivo`.

## Runtime hooks

| Domain | When recorded |
| --- | --- |
| Work order / estimate | create, update (incl. approve/reject), cloned estimate create |
| Incident | create; status change via incident lines |
| Evaluation | create, update when status changes |
| Technician request | create, update, cancel, finish-on-attach, screening create |

## Import notes (future)

1. Map legacy `modelo_id` → `document_type` and `relacion_id` → new document id via mapping tables.
2. Map shared `estados` ids → domain status catalog ids (already seeded with preserved ids where applicable).
3. Do **not** reuse legacy historial ids as v2 PKs.
4. Optionally backfill system chat lines from historial if chat messages were not imported.
