# 02 — Field mapping

**Do not invent transforms.** `UNRESOLVED — REQUIRES REVIEW` means stop.

**Mapping types:** `direct` · `rename` · `transform` · `computed` · `constant` · `default` · `FK` · `conditional` · `split` · `merge` · `deprecated` · `ignored` · `unresolved`

**Required** = the **new** column is required by schema/validation (not that the legacy column is NOT NULL).

---

## How to read this

A single legacy row often writes **two** v2 rows (`companies` + `company_relationships`). Identity fields go to `companies`. Customer/technician/supplier profile fields go to `company_relationships`.

FKs that point at operational entities must go through **ID mapping tables** ([05-id-mapping-strategy.md](05-id-mapping-strategy.md)). Catalog FKs that v2 seeders preserve (status IDs, many type IDs) may be copied **only after production IDs are verified**.

---

## 1. `empresas` → `companies` (operating company)

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| empresas | id (string) | companies | id | unresolved | new bigint; store string in map | yes (PK) | Never copy string PK into bigint | high |
| empresas | id | companies | slug | computed | `Company::uniqueSlugFromName(nombre)` or reviewed slug (OR/ORIL seeder uses `or` / `oril`) | yes | Unique | medium |
| empresas | nombre | companies | name | rename | trim | yes | Fillable only lists `id`, `nombre` | high |
| empresas | *(none)* | companies | kind | constant | `operating_company` | yes | | high |
| empresas | *(none)* | companies | tax_id | unresolved | Seeder has B66409087 / IE4115369FH; column not on Empresa fillable | no | **UNRESOLVED — REQUIRES REVIEW** live columns | unresolved |
| empresas | *(none)* | companies | is_active | default | true | yes | | medium |

Other `empresas` columns: **UNRESOLVED** — model fillable is incomplete vs production. Re-dump schema.

---

## 2. `clientes` → `companies` (party) + `company_relationships` (customer)

### 2a. Party identity → `companies`

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| clientes | id | companies | id | transform | new ID; map `clientes:{id}` | yes | | high |
| clientes | razon_social | companies | name | rename | trim; fallback `nombre_comercial` if empty | yes | Legal name preferred | high |
| clientes | nombre_comercial | companies | tradename | rename | trim | no | | high |
| clientes | *(none)* | companies | slug | computed | `uniqueSlugFromName(name)` | yes | Unique 64 chars | high |
| clientes | nif | companies | tax_id | transform | trim; empty → NULL (empty string collides unique) | no | Unique among non-deleted. **Do not merge** duplicate NIFs without review | high |
| clientes | *(none)* | companies | kind | constant | `party` | yes | | high |
| clientes | pais_id | companies | country_id | FK | country ID map / seeder IDs | no | Confirm `paises` ↔ `countries` | medium |
| clientes | *(person_type not on Cliente)* | companies | person_type | unresolved | Technician has `F`/`J`; clients may not | no | **UNRESOLVED** | unresolved |
| clientes | email | companies | email | transform | trim; lowercase **not** confirmed in legacy | no | v2 validates `email` | medium |
| clientes | telefono | companies | phone | rename | trim; max 50 | no | No confirmed E.164 rule | medium |
| clientes | direccion_1 | companies | address_line_1 | rename | trim | no | | high |
| clientes | direccion_2 | companies | address_line_2 | rename | trim | no | | high |
| clientes | poblacion | companies | city | rename | trim | no | | high |
| clientes | provincia | companies | province_id | unresolved | Legacy column is `provincia` (not `provincia_id`). Type vs `provinces.id` unknown | no | **UNRESOLVED — REQUIRES REVIEW** | unresolved |
| clientes | codigo_postal | companies | postal_code | rename | trim; max 20 | no | | high |
| clientes | idioma_id | companies | language_id | FK | language catalog map | no | | medium |
| clientes | *(none)* | companies | is_active | default / conditional | true unless business says otherwise | yes | Client `estado` is on **relationship**, not company | medium |
| clientes | marca_id | companies | brand_id | unresolved | Brand is a **relationship** field in v2; `companies.brand_id` exists but UI treats brand on relationship | no | Prefer relationship.brand_id | medium |
| clientes | id_fixner / FileMaker | companies | legacy_erp_id | unresolved | Confirm live column name | no | Index exists | low |
| clientes | latitud / longitud | companies | latitude / longitude | rename | decimal 10,7 | no | Confirm columns exist on clientes | low |

### 2b. Customer profile → `company_relationships`

Owner: operating company (OR/ORIL/… — **UNRESOLVED which**). Related: party from 2a. Kind: `customer`.

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| clientes | *(derived)* | company_relationships | owner_company_id | FK | operating-company map | yes | CHECK owner ≠ related | high |
| clientes | id | company_relationships | related_company_id | FK | party company map | yes | | high |
| clientes | *(none)* | company_relationships | kind | constant | `customer` | yes | | high |
| clientes | estado | company_relationships | status | unresolved | Cast boolean in model; v2 enum `prospect\|active\|blocked\|inactive\|archived` | yes (default active) | **UNRESOLVED** 1/0 → which strings | unresolved |
| clientes | is_intercompany | company_relationships | classification | conditional | `intercompany` if true else default `commercial` | yes | Other classifications have no legacy source | medium |
| clientes | codigo | company_relationships | owner_reference | rename | trim | no | Client code in Optima | high |
| clientes | marca_id | company_relationships | brand_id | FK | brand map; NOT NULL in legacy | no in v2 | Required in practice for customers | high |
| clientes | delegacion_id | company_relationships | delegation_id | FK | delegation map | no | | high |
| clientes | billing_language_id | company_relationships | billing_language_id | FK | language map | no | Distinct from company.language_id | high |
| clientes | serie_id | company_relationships | series_id | FK | series map | no | | high |
| clientes | integracion_id | company_relationships | integration_id | FK | integration map | no | | high |
| clientes | integracion_relacion_id | company_relationships | integration_external_id | rename | stringify | no | Confirm type | medium |
| clientes | cliente_reportado_id | company_relationships | reported_customer_relationship_id | FK | map *other* cliente → **its relationship id**, not company id | no | Two-step map | high |
| clientes | responsable_ots_correctivas_id | company_relationships | corrective_work_order_owner_id | FK | user map | no | Must be company_user member **after** membership exists | high |
| clientes | responsable_ots_preventivas_id | company_relationships | preventive_work_order_owner_id | FK | user map | no | | high |
| clientes | responsable_qc_id | company_relationships | quality_owner_id | FK | user map | no | | high |
| clientes | responsable_cliente_id | company_relationships | account_owner_id | FK | user map | no | | high |
| clientes | responsable_comercial_id | company_relationships | commercial_owner_id | FK | user map | no | | high |
| clientes | observaciones | company_relationships | notes | rename | | no | | high |
| clientes | observaciones_privadas | company_relationships | internal_notes | rename | | no | | high |
| clientes | observaciones_aviso | company_relationships | notes_alert | rename | boolean | no | | high |
| clientes | observaciones_privadas_aviso | company_relationships | internal_notes_alert | rename | boolean | no | fillable vs cast name `observaciones_privadas_avisos` — **UNRESOLVED** typo | medium |
| clientes | onboarding | company_relationships | onboarding_notes | rename | | no | | high |
| clientes | comentarios_facturacion | company_relationships | billing_comments | rename | | no | | high |
| clientes | arquetipo | company_relationships | archetype | rename | | no | | high |
| clientes | agrupar_ots_sin_coste | company_relationships | group_zero_cost_work_orders | rename | boolean | no | | high |
| clientes | cargar_lm_en_correctivo | company_relationships | load_materials_on_corrective | rename | boolean | no | | high |
| clientes | agrupar_preventivos_y_correctivos | company_relationships | group_preventive_and_corrective | rename | boolean | no | | high |
| clientes | agrupar_preventivos_por | company_relationships | group_preventives_by | transform | `cliente`→`customer`, `establecimiento`→`establishment`, `ot`→`work_order` | no | Confirmed enum both sides | high |
| clientes | agrupar_correctivos_por | company_relationships | group_correctives_by | transform | same map | no | | high |
| clientes | facturar_fin_mes | company_relationships | invoice_at_month_end | rename | boolean | no | | high |
| clientes | po_requerida | company_relationships | requires_purchase_order | rename | boolean | no | | high |
| clientes | solicitante_requerido | company_relationships | requires_requester | rename | boolean | no | | high |
| clientes | es_franquicia | company_relationships | is_franchise | rename | boolean | no | | high |
| clientes | justificacion_obligatoria | company_relationships | requires_justification | rename | boolean | no | | high |
| clientes | facturacion_envio_automatico | company_relationships | auto_send_invoices | rename | boolean | no | | high |
| clientes | facturacion_envio_individual | company_relationships | send_invoices_individually | rename | boolean | no | | high |
| clientes | send_debt_reminder | company_relationships | send_debt_reminders | rename | boolean | no | plural in v2 | high |
| clientes | contactable_qc | company_relationships | is_quality_control_contactable | rename | boolean | no | | high |
| clientes | requires_client_informed_check | company_relationships | requires_client_informed_check | direct | boolean | no | | high |
| clientes | requires_intervention_scheduled_check | company_relationships | requires_intervention_scheduled_check | direct | boolean | no | | high |
| clientes | requires_budget_approval_limit | company_relationships | requires_budget_approval_limit | direct | boolean | no | | high |
| clientes | is_intercompany | company_relationships | is_intercompany | direct | boolean | no | | high |
| clientes | dias_cierre_presupuesto | company_relationships | quote_close_days | rename | unsigned int | no | | high |
| clientes | meetings_frequency_recurring | company_relationships | recurring_meeting_frequency | rename | | no | | high |
| clientes | meetings_frequency_feedback_sales | company_relationships | sales_feedback_meeting_frequency | rename | default 0 in legacy | no | | high |
| clientes | facturacion_revisada | company_relationships | is_invoicing_reviewed | rename | boolean | no | `invoicing_reviewed_at` has **no** legacy source | medium |
| clientes | correo_revisado | company_relationships | is_email_reviewed | rename | boolean | no | | high |
| clientes | *(none)* | company_relationships | deleted_token | default | `''` | yes | Unique key component | high |

### 2c. `clientes` fields with no v2 destination

```text
clientes.nif duplicates across rows
    ↓
No automatic merge
    ↓
Unique tax_id in v2
    ↓
requires manual decision
```

Any client-only invoice/OT counters not listed above: **ignored / future domain**.

---

## 3. `proveedores` → `companies` (party) + `company_relationships` (supplier)

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| proveedores | nombre_fiscal | companies | name | rename | trim; fallback nombre_comercial | yes | | high |
| proveedores | nombre_comercial | companies | tradename | rename | trim | no | | high |
| proveedores | nif | companies | tax_id | transform | trim; empty → NULL | no | Collision with clientes **UNRESOLVED** | high |
| proveedores | correo | companies | email | rename | trim | no | | high |
| proveedores | telefono | companies | phone | rename | | no | | high |
| proveedores | direccion_* / poblacion / cp / pais_id | companies | address_* / city / postal_code / country_id | rename / FK | | no | provincia same issue as clients | medium |
| proveedores | *(none)* | companies | kind | constant | `party` | yes | | high |
| proveedores | codigo | company_relationships | owner_reference | rename | | no | | high |
| proveedores | delegacion_id | company_relationships | delegation_id | FK | | no | | high |
| proveedores | impuesto | company_relationships | tax_rate | rename | decimal 10,2 | no | | high |
| proveedores | observaciones* | company_relationships | notes / internal_notes / alerts | rename | | no | | high |
| proveedores | revisado | company_relationships | is_reviewed | rename | boolean | no | | high |
| proveedores | activo | company_relationships | status | unresolved | boolean vs 5-value enum | yes | **UNRESOLVED** | unresolved |
| proveedores | *(none)* | company_relationships | kind | constant | `supplier` | yes | | high |

---

## 4. `tecnicos` → `companies` (party) + `company_relationships` (technician)

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| tecnicos | nombre_fiscal | companies | name | rename | trim; fallback nombre_comercial | yes | | high |
| tecnicos | nombre_comercial | companies | tradename | rename | | no | | high |
| tecnicos | nif | companies | tax_id | transform | trim; empty → NULL | no | | high |
| tecnicos | correo | companies | email | rename | | no | | high |
| tecnicos | telefono | companies | phone | rename | | no | | high |
| tecnicos | pagina_web | companies | website | rename | | no | | high |
| tecnicos | direccion_* / poblacion / cp | companies | address_* / city / postal_code | rename | | no | | high |
| tecnicos | pais_id | companies | country_id | FK | | no | | high |
| tecnicos | residence_country_id | companies | residence_country_id | FK | | no | | high |
| tecnicos | person_type | companies | person_type | direct | `F` / `J` (same chars as v2 `PersonType`) | no | Confirmed | high |
| tecnicos | numero_empleados | companies | employee_count | rename | unsigned int | no | | high |
| tecnicos | latitud / longitud | companies | latitude / longitude | rename | | no | | high |
| tecnicos | idioma_id | companies | language_id | FK | default English in model | no | | high |
| tecnicos | id_fixner | companies | legacy_erp_id | rename | stringify | no | | high |
| tecnicos | *(none)* | companies | kind | constant | `party` | yes | | high |
| tecnicos | codigo / codigo_tecnico | company_relationships | owner_reference / external_code | unresolved | Two codes; which is which | no | **UNRESOLVED** both vs one field | unresolved |
| tecnicos | delegacion_id | company_relationships | delegation_id | FK | | no | | high |
| tecnicos | impuesto | company_relationships | tax_rate | rename | | no | | high |
| tecnicos | tarifas | company_relationships | rates_notes | rename | | no | | medium |
| tecnicos | observaciones* | company_relationships | notes / internal_notes / alerts | rename | | no | | high |
| tecnicos | puntuacion_or | company_relationships | optima_score | rename | decimal | no | | high |
| tecnicos | puntuacion_cliente | company_relationships | customer_score | rename | | no | | high |
| tecnicos | puntuacion_media | company_relationships | average_score | rename | | no | | high |
| tecnicos | puntuacion_or_num | company_relationships | optima_score_count | rename | | no | | high |
| tecnicos | puntuacion_cliente_num | company_relationships | customer_score_count | rename | | no | | high |
| tecnicos | prl | company_relationships | has_health_and_safety | rename | boolean | no | | high |
| tecnicos | es_tecnico | company_relationships | is_field_technician | rename | boolean | no | | high |
| tecnicos | es_acreedor | company_relationships | is_creditor | rename | boolean | no | | high |
| tecnicos | es_vip | company_relationships | is_vip | rename | boolean | no | | high |
| tecnicos | disponible_24h | company_relationships | is_available_24h | rename | boolean | no | | high |
| tecnicos | hora_dia_inicio / fin | company_relationships | day_start_at / day_end_at | rename | time | no | | high |
| tecnicos | tiene_embargo | company_relationships | has_garnishment | rename | boolean | no | | high |
| tecnicos | whatsapp_message_authorisation | company_relationships | whatsapp_messaging_authorized | rename | boolean | no | | high |
| tecnicos | encontrado_por_id | company_relationships | sourced_by_user_id | FK | user map | no | | high |
| tecnicos | revisado | company_relationships | is_reviewed | rename | | no | | high |
| tecnicos | fecha_alta | company_relationships | registered_at | transform | date → timestamp | no | | medium |
| tecnicos | estado_id | company_relationships | legacy_status_id | FK / copy | technician status catalog ID | no | Not `status` enum | high |
| tecnicos | estado_id | company_relationships | status | unresolved | Cannot map estados IDs to prospect/active/… without a table | yes | **UNRESOLVED** | unresolved |
| tecnicos | responsable_id | company_relationships | account_owner_id | FK | user map | no | Best-fit; confirm vs quality_owner | medium |
| tecnicos | activo | company_relationships | *(dropped in legacy)* | deprecated | Do not read if column gone | — | Fillable stale | high |
| tecnicos | *(none)* | company_relationships | kind | constant | `technician` | yes | | high |

---

## 5. `users` → `users`

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| users | id | users | id | transform | new ID + `migration_user_map` | yes | Never assume equality | high |
| users | name | users | name | direct | | yes | | high |
| users | email | users | email | transform | trim; unique. Lowercase **not** confirmed | yes | Collisions must go to 08 | high |
| users | password | users | password | direct | keep hash | yes | Do not re-hash | high |
| users | locale | users | locale | direct | | no | | high |
| users | manager_id | users | manager_id | FK | user map (two-pass) | no | | high |
| users | team_leader_id | users | team_leader_id | FK | user map | no | | high |
| users | equipo_id | users | team_id | FK | team map | no | | high |
| users | zona_horaria_id | users | timezone_id | FK | timezone map | no | | high |
| users | marca_id (trait) | users | brand_id | FK | brand map | no | Confirm column | medium |
| users | telefono | users | phone | rename | | no | | high |
| users | pbx_extension | users | pbx_extension | direct | | no | | high |
| users | external_hr_id | users | external_hr_id | direct | | no | | high |
| users | activo | users | is_active | rename | boolean | yes | | high |
| users | empleado_optima | users | is_internal_employee | rename | boolean | no | Membership rule **UNRESOLVED** | high |
| users | es_usuario_equipo | users | is_team_account | rename | boolean | no | | high |
| users | factor_x | users | performance_factor | rename | decimal 5,2 default 1 | no | | high |
| users | objetivo_eur_facturados | users | invoiced_revenue_target | rename | | no | | high |
| users | puntuacion_qc | users | quality_score | rename | | no | | high |
| users | saldo | users | balance | rename | | no | Manual QCoin adjust also updates this (accion 14) | high |
| historial_qcoins | user_id | quality_score_ledger | user_id | FK | user map | no | | high |
| historial_qcoins | causante_id | quality_score_ledger | caused_by_user_id | FK | user map | no | Auth user who triggered credit | high |
| historial_qcoins | accion_id | quality_score_ledger | action_id | FK | catalog `actions` IDs preserved | no | | high |
| historial_qcoins | qcoins_anteriores | quality_score_ledger | previous_score | rename | | no | | high |
| historial_qcoins | qcoins_nuevos | quality_score_ledger | new_score | rename | | no | | high |
| historial_qcoins | qcoins | quality_score_ledger | delta | rename | Signed amount applied | no | | high |
| historial_qcoins | created_at / updated_at | quality_score_ledger | timestamps | direct | | no | | high |
| users | budget_approval_limit | users | budget_approval_limit | direct | | no | | high |
| users | login_unicamente_sso | users | sso_only | rename | boolean | no | | high |
| users | must_change_password | users | must_change_password | direct | | no | | high |
| users | totp_secret | users | totp_secret | direct | | no | | high |
| users | remember_token | users | remember_token | direct | | no | | high |
| users | email_verified_at | users | email_verified_at | direct | | no | | high |
| users | establecimiento_id | users | *(none)* | unresolved | No v2 column | — | Client-portal user | unresolved |
| users | tenant_id | users | *(none)* | unresolved | v2 has tenants/SSO tables; mapping not confirmed | — | | unresolved |
| users | username | users | username | unresolved | Confirm legacy column | no | Unique in v2 | unresolved |
| users | *(none)* | users | active_company_id | FK | after company_user | no | Default operating company **UNRESOLVED** | unresolved |

Passwords: copy hashes only if algorithm matches (bcrypt). **UNRESOLVED** if hashes were upgraded.

---

## 6. `marcas` → `brands`

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| marcas | nombre | brands | name | rename | Store unique; app uppercases on **create** — import must match uniqueness rule | yes | Duplicate names fail | high |
| marcas | corporation_company_id | brands | corporation_company_id | FK | CRM/company overlay — **UNRESOLVED** vs v2 companies | no | Added after brands table; may point at legacy CRM companies | unresolved |
| marcas | responsable_id | brands | account_manager_id | FK | user map | no | | high |
| marcas | responsable_comercial_id | brands | commercial_manager_id | FK | user map | no | | high |
| marcas | periodicidad_reunion_fidelizacion | brands | loyalty_meeting_frequency | rename | string | no | | high |
| marcas | minutos_reunion | brands | *(none)* | deprecated | No v2 column | — | | high |
| marcas | contactable_qc | brands | is_quality_control_contactable | rename | boolean; v2 default true | no | | high |
| marcas | send_debt_reminders | brands | send_debt_reminders | direct | boolean; v2 default true | no | | high |

`marcas_usuarios` → **not** `brand_collaborators` automatically (`brand_collaborators` is the typed collaborator pivot). **UNRESOLVED** whether brand users are collaborators, company_user, or both.

---

## 7. `establecimientos` → `establishments`

| Legacy Table | Legacy Field | New Table | New Field | Mapping Type | Transformation | Required | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| establecimientos | cliente_id | establishments | company_id | FK | **party** company from clientes map, not operating company | yes | Unique with `code` | high |
| establecimientos | nombre | establishments | name | rename | | yes | | high |
| establecimientos | codigo | establishments | code | rename | empty → NULL (unique `(company_id, code)`) | no | | high |
| establecimientos | codigo_tienda | establishments | store_code | rename | | no | | high |
| establecimientos | codigo_tienda_2 | establishments | alternate_store_code | rename | | no | | high |
| establecimientos | telefono | establishments | phone | rename | | no | | high |
| establecimientos | correo | establishments | email | rename | | no | | high |
| establecimientos | correos | establishments | emails | rename | text | no | | high |
| establecimientos | correos_destinatarios | establishments | recipient_emails | rename | | no | | high |
| establecimientos | direccion_1 / 2 | establishments | address_line_1 / 2 | rename | | no | | high |
| establecimientos | poblacion | establishments | city | rename | | no | | high |
| establecimientos | provincia | establishments | province_id | unresolved | same as clients | no | | unresolved |
| establecimientos | codigo_postal | establishments | postal_code | rename | | no | | high |
| establecimientos | pais_id | establishments | country_id | FK | | no | | high |
| establecimientos | zona_horaria_id | establishments | timezone_id | FK | | no | | high |
| establecimientos | idioma_id | establishments | language_id | FK | | no | | high |
| establecimientos | tipos_establecimiento_id | establishments | establishment_type_id | FK | type catalog | no | | high |
| establecimientos | delegacion_id | establishments | delegation_id | FK | | no | | high |
| establecimientos | serie_id | establishments | series_id | FK | | no | | high |
| establecimientos | cliente_facturacion_id | establishments | billing_company_id | FK | party company map (billing **client**, not operating org) | no | | high |
| establecimientos | responsable_id | establishments | responsible_user_id | FK | user map | no | | high |
| establecimientos | estado | establishments | is_active | rename | boolean | yes | | high |
| establecimientos | importante_cliente | establishments | is_client_priority | rename | boolean | no | | high |
| establecimientos | revisado | establishments | is_reviewed | rename | | no | | high |
| establecimientos | revisado_email | establishments | is_email_reviewed | rename | | no | | high |
| establecimientos | prl_centro | establishments | has_site_health_and_safety | rename | boolean | no | | high |
| establecimientos | prl_cliente | establishments | has_customer_health_and_safety | rename | boolean | no | | high |
| establecimientos | contactable_qc | establishments | is_quality_control_contactable | rename | | no | | high |
| establecimientos | parking | establishments | has_parking | rename | | no | | high |
| establecimientos | ulez | establishments | is_ulez_zone | rename | | no | | high |
| establecimientos | latitud / longitud | establishments | latitude / longitude | rename | | no | | high |
| establecimientos | iva_valor | establishments | tax_rate | rename | | no | | high |
| establecimientos | iva_incluido | establishments | tax_included | rename | boolean | no | | high |
| establecimientos | observaciones* | establishments | notes / internal_notes / alerts | rename | | no | | high |
| establecimientos | nora_franjas_horario | establishments | voicebot_time_slots | rename | JSON | no | | medium |
| establecimientos | integracion_relacion_id | establishments | integration_external_id | rename | | no | | medium |
| establecimientos | marca() relation | establishments | *(none)* | ignored | No `marca_id` column; brand via client → relationship | — | Stale relation on model | high |

---

## 8. Catalogs (ID-preserving when seeder matches production)

| Legacy | New | Mapping | Confidence |
| --- | --- | --- | --- |
| paises | countries | rename; **verify IDs** | medium |
| provincias | provinces | rename; **verify IDs** | medium |
| idiomas | languages | rename; **verify IDs** | medium |
| zonas_horarias | timezones | rename | medium |
| equipos | teams | rename | medium |
| delegaciones | delegations | transform (`empresa_id` string → company) | high |
| series | series | rename | medium |
| estados (subset) | `*_statuses` | split + **keep seeded IDs** | high if IDs match |
| incidencias_tipos | incident_types | rename | high |
| incidencias_gravedades | incident_severities | check if v2 table exists — **priorities** exist (`incident_priorities`) | medium |
| tecnicos_incidencias_tipos | technician_incident_types | rename | high |
| contratos_tipos | contract_types | confirm v2 table — **UNRESOLVED** (contracts have status, not type column) | unresolved |
| evaluaciones_tipos / subtipos | evaluation_types / subtypes | confirm tables | medium |
| puestos | job_titles | skip legacy id 15 (seeder) | high |
| documentos pago | payment_documents | skip id 3 | high |
| estimate + OT statuses | work_order_statuses.kind | skip id 2 | high |

---

## 9. QC incidents `incidencias` → `incidents`

Confirmed by v2 migration header.

| Legacy | New | Type | Notes | Confidence |
| --- | --- | --- | --- | --- |
| asunto | subject | rename | required | high |
| comentario | comment | rename | | high |
| estado_id | incident_status_id | FK | preserved catalog IDs 89, 91, 92, 94, 150, 170 | high |
| prioridad_id | incident_priority_id | FK | | high |
| tipo_id | incident_type_id | FK | | high |
| subtipo_id | incident_subtype_id | FK | | high |
| fecha_control | control_at | rename | datetime | high |
| fecha_cierre | closed_at | rename | | high |
| tiempo | duration_seconds | rename | | high |
| tiempo_qc | qc_duration_seconds | rename | | high |
| solicitante_id | requester_user_id | FK | user map | high |
| responsable_id | responsible_user_id | FK | user map | high |
| responsable_qc_id | qc_responsible_user_id | FK | user map | high |
| origen_modelo_id + origen_relacion_id | origin_type + origin_id | transform | `origin_type` ∈ {`establishment`,`company`,`brand`}. Modelo 7→establishment, 6→company (party map), 5→brand. Other modelos **UNRESOLVED** | medium |
| relacion_modelo_id + relacion_relacion_id | related_type + related_id | transform | v2 `related_type` only `evaluation` (modelo 21). Others dropped | medium |
| *(computed)* | establishment_id | computed | from origin establishment or evaluation.establishment | high |
| *(computed)* | evaluation_id | computed | when related is evaluation | high |
| fecha_limite, fecha_recibida, fecha_ultima_revision | — | ignored | documented dead in v2 migration | high |
| establecimiento_id / evaluacion_id columns | — | unresolved | Dropped 2024-10-10; fillable stale | unresolved |

---

## 10. Technician incidents `tecnicos_incidencias` → `technician_incidents`

| Legacy | New | Type | Notes | Confidence |
| --- | --- | --- | --- | --- |
| status / estado_id | status_id | FK | IDs 96, 97, 98, 155, 156, 173 | high |
| tipo_id | technician_incident_type_id | FK | required in v2 | high |
| texto / incident text | incident_text | rename | confirm column name on live schema | medium |
| response | response_text | rename | | medium |
| requested_by | requested_by_id | FK | user map | high |
| responded_by | responded_by_id | FK | | high |
| responded_at | responded_at | direct | **date** not datetime in v2 | high |
| tecnico_id | technician_id | FK | **`company_relationships` id** (technician kind), not `companies.id` | high |
| verificado | is_verified | rename | | high |
| verified_at / by | verified_at / verified_by_id | rename / FK | | high |
| due | due_at | rename | datetime | medium |
| negotiation flags | negotiation_succeeded / unsuccessful_negotiation_solution | rename | | medium |
| abierta, num_facturas, invoice pivot | — | ignored | documented dead | high |

---

## 11. `contratos` → `contracts`

| Legacy | New | Type | Notes | Confidence |
| --- | --- | --- | --- | --- |
| codigo | code | rename | | high |
| cliente_id | company_id | FK | **party** company | high |
| responsable_id | responsible_user_id | FK | | high |
| estado_id | contract_status_id | FK | IDs 77, 95, 78, 79, 80 | high |
| idioma_id | language_id | FK | | high |
| descripcion | description | rename | | high |
| asunto_ot | work_order_subject | rename | | high |
| progreso / total_progreso | progress / total_progress | rename | | high |
| importe_total | total_amount | rename | decimal 10,2 | high |
| fecha_firma | signed_at | rename | | high |
| canceled_date | canceled_at | rename | | high |
| coste, ots_tipo_id, seguimiento, fecha_inicio, fecha_fin | — | ignored | dropped in legacy / not in v2 | high |
| contratos_establecimientos | contract_establishment | transform | map both FKs | high |

---

## 12. `evaluaciones` → `evaluations`

| Legacy | New | Type | Notes | Confidence |
| --- | --- | --- | --- | --- |
| asunto | subject | rename | | high |
| id_publico | public_id | transform | UUID unique; generate if missing/invalid | yes | high |
| establecimiento_id | establishment_id | FK | establishment map; required | high |
| estado_id | evaluation_status_id | FK | IDs 73–76, 99, 117, 133, 134, 139 | high |
| responsable_id | responsible_user_id | FK | | high |
| fecha_proxima_accion | next_action_at | rename | | high |
| fecha_cierre | closed_at | rename | | high |
| pregunta_facility / pregunta_tecnico | facility_question / technician_question | rename | | high |
| visitas | visit_count | rename | default 0 | high |
| tiempo_qc | qc_duration_minutes | rename | **minutes** not seconds | high |
| num_llamadas | call_count | rename | | high |
| primer_intento_contacto | first_contact_attempt_at | rename | | high |

---

## 13. Articles and vehicles

| Legacy | New | Type | Notes | Confidence |
| --- | --- | --- | --- | --- |
| articulos.codigo | articles.code | rename | unique | high |
| articulos.borrable | articles.is_deletable | rename | | high |
| articulos.nombre / descripcion / precio / cliente_id / tipo_id | — | split | names in `article_languages`; price in `article_clients` | high |
| articulo_idiomas | article_languages | transform | language_id map | high |
| articulo_cliente.cliente_id | article_clients.company_relationship_id | FK | **customer relationship id**, not company id | high |
| articulo_cliente precio | article_clients.sale_price | rename | decimal 10,2 required | high |
| vehiculos.marca / modelo / matricula | vehicles.brand / model / license_plate | rename | | high |
| vehiculos.tecnico_id | vehicles.company_relationship_id | FK | technician **relationship** | high |

---

## 14. Collaborators `colaboradores`

| Legacy | Condition | New table | Confidence |
| --- | --- | --- | --- |
| user_id + relacion_id | modelo_id = 5 Marca | brand_collaborators | high |
| user_id + relacion_id | modelo_id = 6 Cliente | company_relationship_collaborators (customer rel) | high |
| user_id + relacion_id | modelo_id = 3 Tecnico | company_relationship_collaborators (technician rel) | high |
| user_id + relacion_id | modelo_id = 4 Proveedor | company_relationship_collaborators (supplier rel) | high |
| user_id + relacion_id | modelo_id = 7 Establecimiento | establishment_collaborators | high |
| user_id + relacion_id | modelo_id = 22 Incidencia | incident_collaborators | high |
| user_id + relacion_id | modelo_id = 15 TecnicoIncidencia | technician_incident_collaborators | high |
| user_id + relacion_id | modelo_id = 21 Evaluacion | evaluation_collaborators | high |
| other modelo_id | — | ignore / 08 | high |

`relacion_id` must be remapped to the **new** parent id (relationship id for parties, not company id).

---

## 15. Dangerous schema differences (fields)

| Change | Where | Risk |
| --- | --- | --- |
| Unique `tax_id` | companies | Legacy NIF not unique |
| Unique `slug` | companies | Computed; collisions |
| Unique `(company_id, code)` | establishments | Duplicate codes per client |
| Unique `email` / `username` | users | Duplicate emails |
| Unique `brands.name` | brands | Case / duplicates |
| Unique `articles.code` | articles | |
| Unique `evaluations.public_id` | evaluations | Generate UUID |
| `empresas.id` string → bigint | companies | Mapping table |
| `tecnico_id` → relationship id | technician_incidents, vehicles | Wrong table if copied raw |
| `cliente_id` → company vs relationship | contracts vs article_clients | **Different targets** |
| Boolean / tinyint → string enum | relationship.status | Unmapped |
| `provincia` → `province_id` | parties, sites | Type unknown |
| Empty string vs NULL | all unique nullable columns | MySQL unique |
| `tiempo_qc` seconds vs minutes | incidents vs evaluations | Different units |
| Soft-delete unique `company_user` without deleted_token | membership | Cannot re-attach |
| VARCHAR length | phone 50, tax_id 32, slug 64 | Truncation |
| decimal 10,2 | money | Overflow vs legacy |
| origin morph integer modelo → string type | incidents | Lost types |

---

## 17. Saved filters (`filtros`)

| Source table | Source field | Target table | Target field | Mapping | Transform | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| filtros | nombre | saved_filters | name | rename | | | high |
| filtros | user_id | saved_filters | user_id | FK | user map | | high |
| filtros | modelo_id | saved_filters | page_key | transform | modelo → string key | Main lists only; see 12 | high |
| filtros | json_filtro | saved_filters | filters | rename | JSON column | Drop empty scalars | high |
| filtros | por_defecto | saved_filters | is_default | rename | | One per user+page_key | high |

---

## 16. Compliments (`felicitaciones`)

| Source table | Source field | Target table | Target field | Mapping | Transform | Notes | Confidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| tipos_felicitacion | id / nombre | compliment_types | id / name | ID-preserving | rename | Seeder IDs 1–3 | high |
| felicitaciones | modelo_id + relacion_id | compliments | subject_type + brand_id / company_relationship_id / establishment_id | transform | typed FKs | See 11-compliments.md | high |
| felicitaciones | tipo_felicitacion_id | compliments | compliment_type_id | rename | | | high |
| felicitaciones | comentario | compliments | comment | rename | | | high |
| felicitacion_usuario | user_id / felicitacion_id / puntuacion | compliment_user | user_id / compliment_id / score | rename | | | high |
| archivos (modelo felic.) | … | compliment_attachments | compliment_id, name, path, … | transform | typed | No morph | high |
| asunto / usuario_id / establecimiento_id / header puntuacion | — | — | — | ignore | dead | Dropped in Prod 2024_02_13 | high |
