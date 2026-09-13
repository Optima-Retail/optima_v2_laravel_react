# 06 — Migration order

Order follows **foreign keys in the v2 schema**, not the alphabet. Catalogs that seeders already load can be **verified**, not re-imported.

Phases below assume a **cold import** into an environment that already ran v2 migrations + catalog seeders. Demo users (`admin@optima.test`) should not collide with production emails — **UNRESOLVED** whether to wipe seed data first.

---

## Phase 0 — Preconditions (no row copy)

1. Dump **live** legacy schema (do not trust `mysql-schema.sql`).
2. Checksum catalog IDs vs v2 seeders.
3. Create mapping tables on a **non-production** migration schema.
4. Freeze writes or snapshot source.

---

## Phase 1 — Reference data (no operational FKs, or FKs only among catalogs)

Must exist before any party/user/site row.

| Order | Entity | Why |
| ---: | --- | --- |
| 1.1 | countries, provinces, languages, timezones, currencies, banks | Address/geo FKs |
| 1.2 | teams, job_titles, series, integrations, numbering_patterns | User and relationship FKs |
| 1.3 | work_order_types, work_order_statuses (kind=estimate\|work_order), form_* , payment_*, expense/cost catalogs | Later domains; harmless if seeded |
| 1.4 | incident_types/subtypes/statuses/priorities, technician_incident_types/statuses | Incident FKs |
| 1.5 | contract_statuses, evaluation_statuses, establishment_types, client_priorities, service types | Contract/eval/site |
| 1.6 | articles **catalog** (`articles` + `article_languages`) | Languages must exist; `article_clients` waits for relationships |

**Why before companies:** `companies.country_id`, `language_id`, `province_id`.

---

## Phase 2 — Users (without `active_company_id`, `brand_id` if circular)

`users` are referenced by brands, relationships, establishments, incidents.

| Order | Step | Why |
| ---: | --- | --- |
| 2.1 | Insert `users` with `manager_id`/`team_leader_id`/`brand_id`/`active_company_id` **null** | Break cycles |
| 2.2 | Fill `manager_id` / `team_leader_id` from `migration_user_map` | Self-FK |
| 2.3 | `team_id` / `timezone_id` | Catalogs already present |

**Not yet:** `company_user`, `active_company_id`, `brand_id` (brand table next).

---

## Phase 3 — Operating companies and delegations

| Order | Step | Why |
| ---: | --- | --- |
| 3.1 | Upsert operating `companies` (OR/ORIL / reviewed `empresas`) | Owners of all relationships |
| 3.2 | `delegations` (`company_id` → operating company) | Customer/technician `delegation_id` |
| 3.3 | Map seeder delegations 1 and 61 if they match legacy | Avoid unique/name clashes |

Brands currently have nullable `corporation_company_id` → companies. Create brands **after** operating (and corporation) companies.

---

## Phase 4 — Brands

| Order | Step | Why |
| ---: | --- | --- |
| 4.1 | `brands` (`account_manager_id` / `commercial_manager_id` → users) | Customer relationships require `brand_id` in business rules |
| 4.2 | Optional `users.brand_id` | User → brand FK |
| 4.3 | `brand_collaborators` (modelo 5) | Needs brand + user maps |
| 4.4 | `brand_messages` if imported | After brands |

`corporation_company_id`: skip unless CRM vs v2 company identity is decided.

---

## Phase 5 — Party companies (no relationships yet)

Insert `companies` kind=`party` from `clientes`, `proveedores`, `tecnicos` (no merge). Fill identity fields only. Quarantine unique `tax_id` collisions.

**Why before relationships:** `related_company_id` FK.

---

## Phase 6 — Company relationships

| Order | Step | Why |
| ---: | --- | --- |
| 6.1 | Insert relationships with `reported_customer_relationship_id` **null** | Self-FK |
| 6.2 | Fill `reported_customer_relationship_id` | cliente_reportado |
| 6.3 | User owner FKs (corrective/preventive/QC/account/commercial/sourced_by) | Users exist; membership still optional at DB level |

CHECK: owner ≠ related.

---

## Phase 7 — Membership

| Order | Step | Why |
| ---: | --- | --- |
| 7.1 | `company_user` per **reviewed** rule | Required by app for user selects |
| 7.2 | `users.active_company_id` | After membership |

**Blocked until** the membership rule is decided (08). Import of owner user FKs can still run; the **application** will hide users who are not members.

---

## Phase 8 — Establishments

Needs: party company (client), optional billing company, delegation, series, type, timezone, language, responsible user.

Then `establishment_collaborators` (modelo 7).

---

## Phase 9 — Party-adjacent operational data

| Order | Entity | Depends on |
| ---: | --- | --- |
| 9.1 | `company_relationship_collaborators` | relationships + users |
| 9.2 | `article_clients` | articles + **customer relationships** |
| 9.3 | `vehicles` | **technician relationships** |
| 9.4 | `technician_alternative_delegations` | technician relationships + delegations |
| 9.5 | `company_schedules`, technician service types, client rates | relationships / catalogs as per those migrations |

---

## Phase 10 — Contracts and evaluations

| Order | Entity | Why |
| ---: | --- | --- |
| 10.1 | `contracts` | `company_id` = party; users; statuses |
| 10.2 | `contract_establishment` | contracts + establishments |
| 10.3 | `contract_attachments` | contracts |
| 10.4 | `evaluations` | **establishments required**; generate `public_id` |
| 10.5 | evaluation notes/attachments/collaborators | evaluations |

Evaluations **before** QC incidents so `evaluation_id` / related origin can resolve.

---

## Phase 11 — Incidents

| Order | Entity | Why |
| ---: | --- | --- |
| 11.1 | `incidents` | establishment/evaluation/company/brand maps + user + catalogs |
| 11.2 | incident lines, messages, attachments, collaborators, status exclusions | incidents |
| 11.3 | `technician_incidents` | **relationship** technician_id + users + catalogs |
| 11.4 | technician incident messages / attachments / collaborators | parent incidents |

---

## Phase 12 — Work orders (merged estimates)

Needs: establishments, users, requesters, catalogs, contracts/incidents/evaluations (optional FKs).

| Order | Entity | Why |
| ---: | --- | --- |
| 12.1 | `requesters` | `solicitantes` → party company |
| 12.2 | `work_orders` headers | `source` / parent / invoicing FKs deferred |
| 12.3 | Self-FKs on `work_orders` | Pair links + grouping |
| 12.4 | lines, technicians, collaborators, attachments, checklist completions | Children |
| 12.5 | `tasks_to_perform.document_id` | Points at `work_orders.id` |

See [09-work-orders.md](09-work-orders.md).

## Phase 13 — Still out of scope

Invoices, tickets, stock, treasury, CRM overlay, form **instances**, OT chats/visits/nora.

## Phase 14 — Validation

Run [07-validation-rules.md](07-validation-rules.md) counts and orphan checks. No production cutover in this phase.

---

## Dependency sketch

```text
catalogs
   → users (partial)
      → operating companies → delegations
         → brands → users.brand_id
            → party companies
               → company_relationships
                  → company_user → active_company_id
                     → establishments
                        → contracts / evaluations / article_clients / vehicles
                           → incidents / technician_incidents
                              → requesters → work_orders → lines / technicians / collaborators
```
