# 10 — Quality scores (accion_usuarios / QCoins)

**Status:** schema created in v2. Scoring wired for estimates + work orders. **No production data imported.**

## What it is in Optima Prod

`accion_usuarios` is **not** an audit log of UI clicks. It is the **KPI event ledger** for QCoins:

1. A domain event happens on a document (presupuesto sent, OT closed, …).
2. `QcoinsProcessor::bloqueDeCalculoQcoins(accion_id, model)` filters the document, computes a raw metric, maps it through `kpi_configuraciones`, applies weights from `constantes`.
3. It writes **`accion_usuarios`** (who scored what, against which document).
4. It credits the user via **`historial_qcoins`** and bumps `users.puntuacion_qc`.

### Schema (legacy)

| Column | Meaning |
| --- | --- |
| `user_id` | User who earns the score (usually `responsable_id`) |
| `accion_id` → `acciones` | Metric type (AccionEnum 1–15) |
| `modelo_id` → `modelos` | Document class (1=presupuesto, 2=OT, …) |
| `relacion_id` | Document PK in that table |
| `peso` | Event weight (preventivo / prev-limpieza multipliers) |
| `valor` | KPI fraction after band mapping (or raw for corazones) |
| `valor_max` | Usually 1; corazones can use the KPI itself |

### Triggers that touch presupuesto / OT

| Event | AccionEnum | Source |
| --- | --- | --- |
| First `fecha_envio` on presupuesto | `TIEMPO_ENVIO_PRESUPUESTO` (4) | `PresupuestoObserver` |
| OT open → closed | `OT_REALIZADA_COD_*` by priority (5–8) | `OT` status transition |
| OT call flags | `CORAZON_*` (9–10) | `OTObserver` — **flags not in v2 yet** |

Other acciones (nota QC, incidencias, felicitación) are out of this work-order slice.

---

## v2 mapping

| Legacy | v2 | Notes |
| --- | --- | --- |
| `acciones` | `actions` | ID-preserving seeder; `constante` → `weight_key` |
| `kpi_configuraciones` | `kpi_configurations` | Bands seeded from Prod migrations |
| `accion_usuarios` | `user_action_scores` | `modelo_id`+`relacion_id` → `document_type`+`document_id` |
| `historial_qcoins` | `quality_score_ledger` | `causante_id` → `caused_by_user_id`; `qcoins_*` → `previous_score` / `new_score` / `delta` |
| `users.puntuacion_qc` | `users.quality_score` | Already on users |
| `users.saldo` | `users.balance` | Updated only on manual adjust (accion 14), same as Prod |
| `constantes` (weights) | `config/quality_scores.php` | No constantes table in v2 |

### Write path (parity with `User::anyadirQcoins`)

Prod always credits through `User::anyadirQcoins($cantidad, $accion_id)`:

1. Insert `historial_qcoins` (`anteriores`, `nuevos`, `qcoins`, `accion_id`, `causante_id`)
2. Set `puntuacion_qc`
3. If accion = `CAMBIO_PUNTUACION_QC` (14), also bump `saldo`

v2: `User::addQualityScore($amount, $actionId, $causedByUserId)` does the same against `quality_score_ledger` / `quality_score` / `balance`.  
`QualityScoreProcessor` calls that method after writing `user_action_scores`.

### Reads in Prod (not ported yet)

| Consumer | Uses ledger for |
| --- | --- |
| `Top10Controller` | Leaderboards (exclude acciones 14 and 15) |
| `InformeDashboardOperaciones` | Ops dash QCoin totals |
| `User::esUsuarioPreventivo` / `obtenerMetricasKpiUsuario` | Preventivo vs correctivo typology |
| `Equipo` ranking | Team totals |

Those remain **later** (dashboard / Top10 UI). Schema + credit path are ready.

### Document morph rewrite

| Legacy `modelo_id` | Legacy table | v2 `document_type` | `document_id` |
| --- | --- | --- | --- |
| 1 Presupuesto | `presupuestos` | `estimate` | `work_orders.id` |
| 2 OT | `ots` | `work_order` | `work_orders.id` |
| Felicitacion modelo | `felicitaciones` | `compliment` | `compliments.id` |
| other modelos | — | **UNRESOLVED** | Do not import until tables exist |

Historical import of `accion_usuarios` must remap `relacion_id` through the work-order ID map (estimate vs OT row for pairs).

---

## Runtime behaviour in v2

Service: `App\Domain\QualityScores\Services\QualityScoreProcessor`.

| Trigger | When |
| --- | --- |
| Estimate sent | Estimate reaches status **5** (`Enviado a Cliente`) → sets `sent_at` if empty → scores action 4 |
| Work order closed | Confirmed WO status goes from `is_open=true` → `is_open=false` → scores action 5–8 by `client_priority_id` |
| Compliment create/update | `QualityScoreProcessor::processCompliment` → action 11 (`document_type=compliment`) |

### Intentionally deferred / different

| Item | Status |
| --- | --- |
| Corazón intervención / recordatorio | Needs OT notification flags skipped in WO schema |
| Business-hours `tiempo_enviado` | Wall-clock hours from `created_at`→`sent_at` until Optima jornada helper exists |
| `qcoins_visitas_minimas` gate | Config `quality_scores.min_weekly_visits` default **0** (Prod=12); full visit join needs evaluations |
| Manual score adjust UI | Later (accion 14) |
| Header QCoins widget | Later |

---

## Seeders

- `ActionSeeder` — AccionEnum IDs 1–15
- `KpiConfigurationSeeder` — bands from `2025_01_20_105246` + `2025_03_05_075832`

Run after migrate: `./vendor/bin/sail artisan db:seed` (or those two classes).
