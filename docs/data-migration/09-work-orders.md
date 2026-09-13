# 09 — Work orders (merged estimates + OTs)

**Status:** schema designed and created in v2. **No production data imported.**

Legacy has two documents: `presupuestos` (estimates) and `ots` (work orders). v2 stores both in **`work_orders`**, distinguished by `stage` and `confirmed_at`.

---

## What already existed in v2 (do not recreate)

These must exist **before** inserting `work_orders` rows (they already do):

| v2 table | Role | Legacy |
| --- | --- | --- |
| `companies` + `company_relationships` | Owner + customer/technician parties | empresas, clientes, tecnicos |
| `establishments` | Site (`establishment_id`) | establecimientos |
| `users` + `company_user` | Responsable / collaborators | users |
| `delegations`, `currencies` | Office / money | delegaciones, monedas |
| `work_order_types` | Type (`ot_tipo_id`) | ots_tipos (IDs 1–54 seeded) |
| `work_order_statuses` | OT + Presupuesto `estado_id` | `kind` = `work_order` \| `estimate`. IDs 10–25, 132, 183 (OT) and 1, 3–9, 115 (presupuesto, skip 2) |
| `client_priorities` | `prioridad_id` | prioridades |
| `articles` + `article_languages` + `article_clients` | Line articles | articulos |
| `checklists` | Status-gated templates | checklists |
| `tasks_to_perform` | Work items | trabajos_a_realizar (`document_id` → `work_orders.id`) |
| `technician_attendance_confirmation_types` | Attendance channel | tecnico_tipos_confirmacion_asistencia (1–3) |
| `contracts`, `incidents`, `evaluations` | Optional FKs | contratos, incidencias, evaluaciones |
| `series`, `client_rates`, service-type catalogs | Rating / typing | not header FKs |

---

## Architectural decision

**Legacy:** approving an estimate (**status 7**) **creates a new OT** (`ots.presupuesto_id`). Rejecting an OT (**status 12**) **creates a new estimate** (`presupuestos.ot_id`). Two rows, two codes, two status catalogs. There is **no** `es_presupuesto` / `fecha_confirmacion`.

**v2 going forward:** one row. Starts as `stage = estimate`. Confirming sets `stage = work_order` and `confirmed_at`. Same id, same children.

**v2 data import of historical pairs:** still **two rows** in `work_orders` (each keeps its code and status), linked by `source_work_order_id`. Do not silently collapse a presupuesto+OT pair into one row — they can diverge after conversion. In-place confirm is the **new** product behaviour.

---

## New tables

| Table | Source | Notes |
| --- | --- | --- |
| `requesters` | `solicitantes` | Not `users`. Name + emails per party company |
| `work_order_technician_statuses` | `estados` 167/168/169 | Attendance on assigned technicians |
| `work_orders` | `presupuestos` ∪ `ots` | Merged header |
| `work_order_lines` | `presupuestos_lineas_facturacion` ∪ `ots_lineas_facturacion` | Keep `sort_order` (OT conversion used to drop it) |
| `work_order_technicians` | `presupuestos_solicitados` ∪ `ot_tecnico` | Quotes + assignment on one child |
| `work_order_collaborators` | `colaboradores` modelo 1 and 2 | Typed pivot |
| `work_order_attachments` | `archivos` modelo 1 and 2 | Same shape as contract attachments |
| `work_order_checklist_completions` | `chequeos` | Instance of a checklist template |

Related QCoins tables (not children of a single WO row, but written when estimates/OTs score): see [10-quality-scores.md](10-quality-scores.md) (`actions`, `user_action_scores`, `kpi_configurations`, `quality_score_ledger`).

`tasks_to_perform.document_id` now points at `work_orders.id` for **both** `document_type` values (`estimate` \| `work_order` = stage at write time). No extra table.

---

## Intentionally not created (dead or other domains)

| Legacy | Why skipped |
| --- | --- |
| `presupuestos_trabajos_a_realizar`, `presupuesto_estados`, `estados_ot`, `estimates` | Dead / leftover catalogs |
| `fecha_entrada`, `email_cliente_*`, text `solicitante`, estado id 2 | Dropped columns |
| 18 OT notification flags, `uuid_aviso_dos_dias_antes` | Comms side-channel; not the document |
| OOH confirmations / `responsable_ooh_id` | Separate ops feature later |
| `plantilla_id`, `informe_id`, `hay_informe` | Files → attachments; templates not in v2 |
| `tipo_documento`, `id_documento`, `tecnico_fixner`, `version_importacion` | FileMaker |
| `ot_padre` boolean | Derived from `parent_work_order_id` |
| `tiempo_enviado` | Computable |
| `iteracion_id`, `pre_orders`, `nora_call_logs`, `ot_graph_emails` | Other domains |
| `ot_visita`, `planificacion_lineas`, `peticiones`, `prl_tickets`, `avisos_app` | Other domains |
| `costes_ots`, `facturas_venta_lineas` | Invoices not in v2 |
| `chats` / `lineas_chats` | Messaging later (follow technician incidents if needed) |
| `historial_cambios_estados` | Status audit later |
| `technician_workorder` | Dropped 2023 |
| `ot_tecnico_articulos` | **Never existed** |

---

## `stage` + `confirmed_at`

Rules are enforced in `WorkOrder` on save (MySQL cannot CHECK columns that also have FK `ON DELETE` actions).

| `stage` | Status FK | `confirmed_at` |
| --- | --- | --- |
| `estimate` | `status_id` required; status.kind = `estimate` | null |
| `work_order` | `status_id` required; status.kind = `work_order` | set |

Default confirm status: **14** Recibida - OK por Organizar (legacy `generarOT`).

v2 UI (React) follows the Livewire OT/presupuesto workflow:

- One list with **stage** (estimate / work order) and **Pendientes** (`status.is_open`).
- Create and edit are the same details form. Status is a select, not a button.
- Estimate → status **7 Aprobado** confirms **in place** (`stage=work_order`, status **14**, `confirmed_at`).
- Work order → status **12 Rechazada - Presupuesto** keeps the OT and **clones a new estimate** (status **1**, `source_work_order_id`), copying header, collaborators and tasks, not billing lines.

---

## Child merge rules

**Lines:** one table. Estimate `orden` → `sort_order`. Amounts `decimal(10,2)`.

**Technicians:** one table. Estimate quote money is nullable; WO attendance `status_id` / confirmation type is nullable until assigned. `company_relationship_id` = technician relationship (never `tecnicos.id`).

**Collaborators / attachments / chequeos:** remapped `relacion_id` + `modelo_id` 1 or 2 → `work_order_id`.

---

## FK resolution (never copy legacy ids except seeded catalogs)

| New FK | Map |
| --- | --- |
| `establishment_id` | `migration_establishment_map` |
| `billing_company_id` | clientes → party company |
| `responsible_user_id`, collaborators | `migration_user_map` |
| `requester_id` | `migration_requester_map` (`solicitantes`) |
| `work_order_type_id`, priorities, statuses | seeded IDs after checksum |
| `work_order_technicians.company_relationship_id` | tecnicos → **relationship** |
| `contract_id` / `incident_id` / `evaluation_id` | those maps |
| `source_work_order_id` | other row in `migration_work_order_map` |

Two map sources: `presupuestos` and `ots` both write `migration_work_order_map`.

---

## Import order (after catalogs + parties + sites)

1. `requesters`
2. `work_orders` headers (`source_work_order_id`, parent, invoicing FKs deferred)
3. Fill self-FKs
4. lines, technicians, collaborators, attachments, checklist completions
5. `tasks_to_perform.document_id`
