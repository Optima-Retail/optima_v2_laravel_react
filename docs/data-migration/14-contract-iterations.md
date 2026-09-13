# 14 — Contract iterations (`iteraciones`)

**Status:** schema + nested CRUD on contracts in v2. **No production row import yet.**

## What it is in Optima Prod

`iteraciones` are **recurrence schedules on a contract**. They drive WO preview generation (`Iteracion::generarOtsPreview()`), optionally share a form template, and optionally belong to a **contract invoicing aggregation** for billing-cycle OT previews.

They are **not** a parent of `visitas` / `planificacion_lineas` (those stay deferred).

## Related tables

| Legacy | Role | v2 | Decision |
| --- | --- | --- | --- |
| `contratos` | **Parent** | `contracts` | Exists |
| `ots_tipos` | Required FK | `work_order_types` | Exists |
| `plantillas` | Optional FK | `form_templates` | Exists |
| `contract_invoicing_aggregations` | Optional parent of iterations | `contract_invoicing_aggregations` | **Import** |
| `iteraciones` | Schedule row | `contract_iterations` | **Import** |
| `ots.iteracion_id` | **Child** link | `work_orders.contract_iteration_id` | **Import** (nullable) |
| `visitas` / `planificacion_lineas` | Planning | — | **Defer** (no FK to iterations) |

## Field mapping (`iteraciones` → `contract_iterations`)

| Legacy | v2 |
| --- | --- |
| `contrato_id` | `contract_id` |
| `ot_tipo_id` | `work_order_type_id` |
| `fecha_inicio` / `fecha_fin` | `starts_on` / `ends_on` |
| `periodicidad` `semanal`\|`mensual` | `periodicity` `weekly`\|`monthly` |
| `tipo_periodicidad` `basica`\|`compleja` | `periodicity_kind` `basic`\|`complex` |
| `iteracion` | `interval` |
| `dias_semana` / `dias_mes` / `meses` | `weekdays` / `month_days` / `months` (JSON) |
| `coste` | `cost_amount` |
| `asunto` | `subject` |
| `establecimientos_id` | `establishment_ids` (JSON) |
| `formulario_plantilla_id` | `form_template_id` |
| `invoicing_aggregation_id` | `invoicing_aggregation_id` |
| `codigo` (fillable only, no column) | — |

## Invoicing aggregations

Full create columns: `contract_id`, `subject`, `billing_frequency`, `billing_day`, `billing_cycle_start`, `per_establishment` (legacy `per_establecimiento`), soft deletes.

Frequencies: `monthly`, `bimonthly`, `quarterly`, `annually`, `biannually`.

## Company scope

Via the contract’s client `company_id` (same accessibility rules as contracts). Form templates offered in the UI are limited to the active owner company.

## Skipped from this change

- WO generation / `generarOtsPreview` job pipeline
- Invoicing cycle OT generation use cases
- Production data import (ID mapping tables)

## UI

Nested on contract create/edit: iterations + invoicing aggregations saved with the contract.
