# Inertia Consolidation Audit

Phase 1 (read-only) of the requested Web/API consolidation. No application code was
modified while producing this report.

## 0. Critical finding — read this before anything else

The refactor brief assumes the `Api/V3` layer is leftover duplication from a
previous architecture ("this application is currently an Inertia + React Laravel
application, not a public API product"). **The repository's own committed
documentation and live UI copy say the opposite.**

- `docs/ARCHITECTURE.md:5` — *"`laravel_optima` is an **API-first** Laravel
  application with an **Inertia + React** control plane. Versioning starts at
  **`/api/v3`**."*
- `docs/ARCHITECTURE.md:65` — auth surfaces table explicitly lists
  `/api/v3/auth/*` and `/api/v3/config/users` as a **Sanctum personal access
  token** surface, distinct from and parallel to the session-based web UI.
- `docs/FRONTEND.md:63` — *"UI copy is English for now. **API remains at
  `/api/v3/*`** (Sanctum tokens), with config resources under
  `/api/v3/config/*`."*
- `resources/js/i18n/locales/en.ts:118` / `es.ts:118` — the **Dashboard page
  itself**, the first screen after login, renders: *"This shell is Inertia +
  React. **Token clients still hit /api/v3.**"* (`Dashboard/Index.tsx:39` calls
  `t('dashboard.description')`, wired from `DashboardController`.)
- `DashboardController.php:20` — passes `'apiVersion' => 'v3'` into the
  dashboard stats shown to every logged-in user.
- `config/sanctum.php`, a `personal_access_tokens` migration
  (`2026_09_03_070151`, four days before this audit), a dedicated
  `AuthController::login`/`me`/`logout`/`logoutAll` token flow, and two
  passing Feature test files (`tests/Feature/Api/V3/Auth/AuthTest.php`,
  `tests/Feature/Api/V3/CompaniesApiTest.php`) that explicitly exercise
  `Sanctum::actingAs()` and JSON envelopes — this is built and tested
  infrastructure, not a stub.

There is no direct evidence *inside this repo* of an already-integrated mobile
app or third-party consumer (no Postman collection, no external README, no
CI config, no partner docs). But the project explicitly declares itself
API-first, ships user-facing copy about "token clients," and has real
Sanctum token issuance/auth tests — this is deliberate, documented, in-flight
product design, not an accidental leftover.

**Per the refactor brief's own rule** ("If `api/v3` contains endpoints that
are genuinely required by an external consumer, DO NOT delete them
automatically... report EXTERNAL API — KEEP"), the whole `Api/V3` surface is
flagged:

```
EXTERNAL API — KEEP (pending your decision)
Reason: docs/ARCHITECTURE.md and docs/FRONTEND.md declare the app "API-first"
with Sanctum token clients at /api/v3 as a first-class, documented surface;
the Dashboard UI itself tells logged-in users "Token clients still hit
/api/v3." No evidence of an already-wired external consumer was found in this
repo, but the intent is explicit and current (docs/migrations dated days ago).
Consumer: undetermined from this repo — likely a mobile app or partner
integration that is either planned or lives outside this repository.
```

**I have not written a Phase 2 plan or touched any code pending your
decision on this.** Two ways to proceed are laid out in §11
("Recommended path"); the rest of this document is the full factual audit
either way.

---

## 1. Current controller structure

```
app/Http/Controllers/
├── Controller.php                          (base, AuthorizesRequests)
├── Concerns/ResolvesActiveCompany.php       (shared trait — used by both surfaces)
├── Web/
│   ├── Auth/AuthenticatedSessionController.php    (session login/logout)
│   ├── Companies/
│   │   ├── CompanyController.php                  (CRUD, Inertia, + /companies/data JSON)
│   │   ├── CompanyMemberController.php             (attach/detach users — no API equivalent)
│   │   ├── CompanyRelationshipController.php       (Clients+Suppliers, Inertia, + /data JSON)
│   │   ├── EstablishmentController.php             (CRUD, Inertia, + /data JSON)
│   │   └── SwitchCompanyController.php             (single-action, redirect back)
│   ├── Config/
│   │   ├── BankController.php, BrandController.php, CountryController.php,
│   │   │   CurrencyController.php, DelegationController.php,
│   │   │   IntegrationController.php, LanguageController.php,
│   │   │   RatingTypeController.php, RoleController.php, SeriesController.php,
│   │   │   TeamController.php, TimezoneController.php, UserController.php,
│   │   │   WorkOrderTypeController.php
│   │   │   — 14 controllers, all CRUD + Inertia + /data JSON (tabulator),
│   │   │     no Api/V3 equivalents except Users.
│   ├── DashboardController.php
│   └── LocaleController.php
└── Api/V3/
    ├── BaseApiController.php                 (success/created/error → ApiResponse)
    ├── Auth/AuthController.php                (login/me/logout/logout-all, Sanctum tokens)
    ├── CompanyController.php                  (CRUD, JSON)
    ├── CompanyRelationshipController.php       (CRUD, JSON, company-scoped)
    ├── EstablishmentController.php             (CRUD, JSON, company-scoped)
    ├── MeCompanyController.php                 (memberships + switch, JSON)
    └── Config/UserController.php                (CRUD, JSON)
```

`Api/V3` is **7 controllers total**, not a parallel copy of the whole `Web`
tree. It covers exactly: Auth, Companies, CompanyRelationships,
Establishments, Me/Company-switch, and Config/Users. The other 14 Config
resources (Banks, Brands, Countries, Currencies, Delegations, Integrations,
Languages, RatingTypes, Roles, Series, Teams, Timezones, WorkOrderTypes) and
`CompanyMemberController` have **no** API/V3 equivalent at all.

## 2. Current route structure

```
routes/
├── web.php            370 lines — all Inertia pages + per-resource /data
│                       JSON endpoints (session `auth` guard, `permission:*`
│                       + `company.context` middleware)
├── api.php             18 lines — mounts routes/api/v3.php under prefix
│                       `v3`, name `api.v3.`
└── api/
    ├── v3.php           32 lines — auth group (public login, sanctum-gated
    │                     me/logout/logout-all), requires config/users.php
    │                     and companies.php
    ├── v3/companies.php 41 lines — me/companies, me/company/switch,
    │                     companies CRUD, relationships CRUD, establishments
    │                     CRUD — all under `auth:sanctum` (+ `company.context`
    │                     for the scoped groups) and `permission:*`
    └── v3/config/users.php 29 lines — users CRUD under `auth:sanctum`
```

`bootstrap/app.php` wires `api.php` under the `api` prefix and treats any
`api/*` or JSON-expecting request as "API" for exception rendering
(`fn ($request) => $request->is('api/*') || $request->expectsJson()`) — i.e.
the framework itself has a first-class notion of an API surface, not just an
incidental route file.

## 3. All Api/V3 controllers (detail)

| Controller | Methods | Auth guard | Response | Shared Service |
|---|---|---|---|---|
| `Auth\AuthController` | login, me, logout, logoutAll | public login; `auth:sanctum` for the rest | `ApiResponse` envelope, Sanctum plain-text token | none (direct `User` query + Hash) |
| `CompanyController` | index/store/show/update/destroy | `auth:sanctum` + `permission:companies.*` | `CompanyResource` | `CompanyService` (same as Web) |
| `CompanyRelationshipController` | index/store/show/update/destroy | `auth:sanctum` + `company.context` + `permission:company_relationships.*` | `CompanyRelationshipResource` | `CompanyRelationshipService` (same as Web) |
| `EstablishmentController` | index/store/show/update/destroy | `auth:sanctum` + `company.context` + `permission:establishments.*` | `EstablishmentResource` | `EstablishmentService` (same as Web) |
| `MeCompanyController` | index (memberships), switch | `auth:sanctum` | `CompanyResource` / raw array | `ActiveCompany` (same as Web `SwitchCompanyController`) |
| `Config\UserController` | index/store/show/update/destroy | `auth:sanctum` + `permission:users.*` | `UserResource` | `UserService` (same as Web) |

All five resource controllers call the **exact same Domain services** as
their Web counterparts (`CompanyService`, `CompanyRelationshipService`,
`EstablishmentService`, `UserService`). Three of them
(`Company{,Relationship}Controller`, `EstablishmentController`) even reuse
the **Web-namespaced Form Requests** (`App\Http\Requests\Web\Companies\Store/UpdateCompanyRequest`,
`Store/UpdateCompanyRelationshipRequest`, `Store/UpdateEstablishmentRequest`,
`SwitchCompanyRequest`) directly — there is *already* request-validation
sharing across the two surfaces despite the `Web` folder name. Only Users
has separate API-specific Form Requests
(`App\Http\Requests\Api\V3\Config\Users\Store/UpdateUserRequest`), because the
API accepts a `roles: string[]` payload shape that differs from the web
form's shape.

**Conclusion: there is no duplicated business logic.** The duplication is
entirely at the thin controller/response-format layer (redirect+Inertia vs.
JSON envelope+Resource), which is the architecturally correct place for two
different client types to diverge, *if* both client types are real.

## 4. All Web controllers

Covered in the tree in §1. All 14 Config controllers plus Companies,
Establishments, and Clients/Suppliers (via `CompanyRelationshipController`)
were already migrated in this session to a consistent pattern: Inertia
`index()` (props only, no paginated data) + a `data()` JSON endpoint used by
the `RemoteDataTable`/Tabulator frontend component, backed by
`App\Support\TabulatorQuery` + `App\Support\TabulatorResponse`. That JSON
`/…/data` endpoint is **not** part of `Api/V3` — it's a `web.php` route,
session-authenticated, CSRF-protected, and returns the raw
`{data, last_page, last_row}` shape Tabulator expects (not the
`{success, message, data}` `ApiResponse` envelope). This is exactly the
"smallest possible Laravel web endpoint" pattern the brief asks for — it
already exists and should **not** be touched by this refactor.

## 5. Duplicate / partially-duplicated controllers

| Resource | Web | Api/V3 | Overlap |
|---|---|---|---|
| Companies | `Web\Companies\CompanyController` (index/create/store/edit/update/destroy + `/data`) | `Api\V3\CompanyController` (index/store/show/update/destroy) | Same `CompanyService`, same `Store/UpdateCompanyRequest`. Web has `create`/`edit` (Inertia form pages) and `/data` (tabulator) that Api has no reason to have. Api has `show` (single JSON record) that Web has no reason to have. **Not true duplication** — different response shapes for different clients over the same domain logic. |
| CompanyRelationships (Clients/Suppliers) | `Web\Companies\CompanyRelationshipController` (indexClients/indexSuppliers/create/store/edit/update/destroy ×2 + dataClients/dataSuppliers) | `Api\V3\CompanyRelationshipController` (index/store/show/update/destroy, kind-agnostic) | Same `CompanyRelationshipService`, same Form Requests. Web splits by `kind` into two named resources (Clients vs Suppliers) for two separate nav items; Api exposes one generic `relationships` collection. Genuinely different shape for a genuinely different consumer model, not accidental duplication. |
| Establishments | `Web\Companies\EstablishmentController` | `Api\V3\EstablishmentController` | Same `EstablishmentService`, same Form Requests. Same pattern as Companies. |
| Users | `Web\Config\UserController` | `Api\V3\Config\UserController` | Same `UserService`. Separate Form Requests (API accepts `roles` differently). Same pattern. |
| Company switch | `Web\Companies\SwitchCompanyController` (redirect `back()`) | `Api\V3\MeCompanyController::switch` (JSON `CompanyResource`) | Same `ActiveCompany` domain class, same `SwitchCompanyRequest`. Different response shape only. |
| Auth | `Web\Auth\AuthenticatedSessionController` (session, redirect) | `Api\V3\Auth\AuthController` (Sanctum token) | **Not the same underlying credential/session model** — one issues a session cookie, the other issues a bearer token. These cannot be merged into one controller without picking one auth mechanism; a shared test (`AuthTest::test_inactive_locked_and_sso_only_users_cannot_use_password_login`) explicitly verifies **both** surfaces enforce the same account restrictions in parallel. |
| Config (13 other resources), CompanyMember | Web only | — | No duplication; nothing to consolidate. |

Every "duplicate" pair shares its Service (and often its Form Request) and
differs only in response format and auth guard — which is the expected shape
for a web app **that also serves a token-based API**, not evidence of
copy-paste architecture debt.

## 6. API routes actually in use

- Exercised by the two existing test files (`AuthTest`, `CompaniesApiTest`):
  `POST /api/v3/auth/login`, `GET /api/v3/auth/me`,
  `GET/POST/PUT/DELETE /api/v3/companies{,/…}`,
  `GET /api/v3/me/companies`, `POST /api/v3/me/company/switch`,
  `GET/POST /api/v3/relationships`, `GET/POST /api/v3/establishments`.
- **Not covered by any test**: `POST /api/v3/auth/logout`,
  `POST /api/v3/auth/logout-all`, `show`/`update`/`destroy` on
  relationships and establishments, and the **entire**
  `Config\UserController` (`/api/v3/config/users/*` has zero test coverage).
- Not called from `resources/js` anywhere (see §7) — the only place
  `/api/v3` appears in the frontend is as a literal string inside translated
  UI copy, not a request.

## 7. Frontend `/api` consumers

Repo-wide search for `axios`, `fetch(`, and `/api` in `resources/js`:

- **Zero** `axios` usage — not a dependency in `package.json`, not imported
  anywhere.
- **Zero** raw `fetch(` calls to `/api/...` or anywhere else outside
  Tabulator's own internal ajax config (`resources/js/support/tabulator.ts`
  → `tabulatorAjaxConfig`, which is a config object handed to the
  `tabulator-tables` library, not a hand-written fetch call, and it hits
  `web.php` `/…/data` routes, never `/api/v3`).
- The only two string matches for `/api/` in all of `resources/js` are the
  translation strings quoted in §0 (`en.ts:118`, `es.ts:118`), which are
  **descriptive copy shown to the user**, not code that calls the API.
- All 22 `resources/js/services/*.ts` files are thin `@inertiajs/react`
  `router.get/post/put/delete` wrappers over `web.php` routes (confirmed
  during this session's Tabulator-consistency work across every one of
  them). None hit `/api`.

**Conclusion: the React/Inertia frontend in this repository does not consume
`Api/V3` at all, today.** If there is a consumer, it is external to this
repository (a native mobile app, a separate SPA, a partner integration) —
consistent with the "API-first... token clients" framing in the docs.

## 8. Controllers used by Inertia pages

All 17 `Web` controllers `Inertia::render(...)` at least one page (see §1/§4
tree — every one was verified directly during this session's Tabulator
migration or freshly read for this audit). None of the `Api/V3` controllers
render Inertia; they all return `JsonResponse` via `BaseApiController`.

## 9. Resources/DTOs that are API-only

| Class | Used by | Verdict |
|---|---|---|
| `App\Http\Resources\Api\V3\CompanyResource` | `Api\V3\CompanyController`, `Api\V3\MeCompanyController` | API-only |
| `App\Http\Resources\Api\V3\CompanyRelationshipResource` | `Api\V3\CompanyRelationshipController` | API-only |
| `App\Http\Resources\Api\V3\EstablishmentResource` | `Api\V3\EstablishmentController` | API-only |
| `App\Http\Resources\Api\V3\UserResource` | `Api\V3\Auth\AuthController`, `Api\V3\Config\UserController` | API-only |

No DTO layer beyond these four `JsonResource` classes and `App\Support\ApiResponse`
(the `{success, message, data, meta}` envelope builder) was found. `ApiResponse`
is used **only** by `BaseApiController` and therefore only by `Api/V3`.
None of the four Resources or `ApiResponse` are referenced by any `Web`
controller, any Inertia page prop, or any test outside `tests/Feature/Api/V3`.
If the API layer is removed, all four Resources + `ApiResponse` +
`BaseApiController` are safe to delete outright.

## 10. Shared Form Requests / Policies / Services / Actions / Domain classes

**Form Requests genuinely shared today** (declared under `Web\Companies\*`
but imported by both surfaces):

- `StoreCompanyRequest`, `UpdateCompanyRequest`
- `StoreCompanyRelationshipRequest`, `UpdateCompanyRelationshipRequest`
- `StoreEstablishmentRequest`, `UpdateEstablishmentRequest`
- `SwitchCompanyRequest`

**Form Requests that are API-only** (would be deleted with the API):

- `App\Http\Requests\Api\V3\Auth\LoginRequest`
- `App\Http\Requests\Api\V3\Config\Users\StoreUserRequest` / `UpdateUserRequest`
  (distinct from `Web\Config\Users\Store/UpdateUserRequest` because of the
  `roles` payload shape difference noted in §3)

**Policies** — one set, `app/Policies/*Policy`, used identically by both
surfaces via `$this->authorize(...)` / `permission:*` middleware. No
API-specific policies exist. Confirmed unaffected either way.

**Services/Domain classes called by both surfaces** (the actual reusable
business logic — untouched regardless of what happens to `Api/V3`):

- `App\Domain\Companies\Services\CompanyService`
- `App\Domain\Companies\Services\CompanyRelationshipService`
- `App\Domain\Companies\Services\EstablishmentService`
- `App\Domain\Config\Users\Services\UserService`
- `App\Domain\Companies\Support\ActiveCompany`
- `App\Http\Controllers\Concerns\ResolvesActiveCompany` (trait, used by both
  `Web\Companies\EstablishmentController`/`CompanyRelationshipController`
  and their `Api\V3` counterparts)

None of these need to change no matter which direction is chosen for the API
layer — this is exactly the separation the brief asks to preserve, and it
already exists.

## 11. Tests depending on the API/V3 layer

- `tests/Feature/Api/V3/Auth/AuthTest.php` — 4 tests (login, me,
  register-is-removed, restricted-account rejection **shared with the web
  login test in the same method**).
- `tests/Feature/Api/V3/CompaniesApiTest.php` — 3 tests (companies CRUD,
  me/companies + switch, relationships/establishments scoping).

That's it — **7 tests total** reference `Api\V3` or `/api/v3` anywhere in
the suite. No other test file touches this layer. If the API is removed,
these 7 tests (and only these) would need to go; `AuthTest`'s
shared-restriction assertion would need its web-side half preserved
elsewhere (it currently lives in the same test method as the API-side half).

## 12. Files that can safely be removed *if and only if* the API is intentionally decommissioned

(None of this should happen without your explicit go-ahead per §0.)

- `app/Http/Controllers/Api/V3/**` (7 files)
- `app/Http/Resources/Api/V3/**` (4 files)
- `app/Http/Requests/Api/V3/**` (3 files)
- `app/Support/ApiResponse.php`
- `routes/api.php`, `routes/api/v3.php`, `routes/api/v3/companies.php`, `routes/api/v3/config/users.php`
- `tests/Feature/Api/V3/**` (2 files) — replaced by preserving the
  shared-behavior assertions in existing Web tests where they overlap
  (e.g. the account-restriction check in `AuthTest`)
- `config/sanctum.php` + the `laravel/sanctum` composer dependency, **only**
  if nothing else uses token auth (nothing currently does)
- `database/migrations/2026_09_03_070151_create_personal_access_tokens_table.php`
  — do **not** drop this via a new migration; per the brief's "no DB changes"
  rule, leave the table in place even if the code stops using it
- The "API-first" framing in `docs/ARCHITECTURE.md`, `docs/FRONTEND.md`, and
  the `dashboard.description` i18n string in both locales would all need to
  be rewritten to stop promising something that no longer exists

## 13. Files requiring migration (if consolidating)

None of the shared Services/Policies/Form Requests need to move — they
already live in reusable locations. The only real "merge" work, if chosen,
is deleting the `Api\V3` controllers/resources/requests/routes/tests above;
there is no business logic inside them to port anywhere, because none of it
lives in the controllers.

## 14. Functionality that would be lost by removing Api/V3

- Bearer-token authentication for any client that isn't a browser holding an
  Inertia session (mobile app, CLI, third-party integration, future SPA).
- Programmatic `logout`/`logout-all` (revoke all personal access tokens) —
  no web equivalent exists or is needed (the web session just expires/logs
  out normally).
- A stable versioned (`/v3`) JSON contract with `success`/`message`/`data`/`meta`
  envelope and explicit `*Resource` field lists — useful for external
  consumers who need a documented, stable shape independent of Inertia prop
  changes. The `/…/data` Tabulator endpoints are internal, undocumented,
  and intentionally not versioned or enveloped this way.
- Whatever the "token clients" referenced in the live UI copy currently are
  or are expected to become.

---

## Risks

1. **Deleting a documented, user-facing, tested product surface based on a
   premise the repo itself contradicts.** This is the primary risk and the
   reason Phase 2/3 have not started.
2. Two of the seven Api/V3 endpoints (`Config\UserController`'s full CRUD,
   `logout`/`logout-all`) have **no test coverage** — if kept, they should
   get tests before further changes are layered on top; if removed, no
   regression risk exists for them specifically, but that also means "no
   one would notice" is not evidence they're unused.
3. Minor pre-existing smell, independent of this decision: Form Requests
   shared by both surfaces live under a `Web\Companies\*` namespace despite
   being consumed by the API too. Worth a rename (e.g. to a
   surface-neutral `Http\Requests\Companies\*`) regardless of the API's
   fate, but it's cosmetic and low-risk — not blocking.

## 15. External-consumer investigation (follow-up)

At your request, I searched beyond this repository for an actual consumer of
`/api/v3`, without modifying anything (this repo or elsewhere).

### What exists on this machine

`~/projects/` contains four entries: this repo (`laravel_optima`), and three
siblings — `optimaback`, `optimafront`, and `fcf-implement`.

**`optimaback` and `optimafront` are real, separately-versioned, currently
maintained Laravel applications**, not sandboxes:

- Both have their own `.git` (GitHub org `Optima-Retail`, `preprod` branch),
  `deploy.php` (deployer.org), `docker-compose.yml`, `CLAUDE.md`/`AGENTS.md`,
  and recent commit activity (`optimaback` modified Sep 1–2, `optimafront`
  Aug 31 — both within the past week).
- `optimaback`'s README describes it as the backend: PDF merging via
  Ghostscript, a WhatsApp bot integration, a HubSpot CRM import, and its
  **own** versioned reporting API — `.env` comment: *"Looker Studio / Apps
  Script -> GET /api/v1/reporting/ots\* (header X-Reporting-Key)"*. That's a
  **different API, at a different version number (`v1`), for a different
  purpose (BI reporting)**, unrelated to `laravel_optima`'s `/api/v3`.
- `optimafront`'s `.env` references *"connect to the back's Reverb (export
  progress and NORA events)"* — i.e. `optimafront` talks to `optimaback` over
  WebSockets (Laravel Reverb), not to `laravel_optima`.
- Neither app's `.env`, `.env.example`, `routes/`, or `config/` contains any
  reference to `laravel_optima`, its app URL (`localhost:8088`), or
  `/api/v3`. They run on their own ports (`optimaback` on `8000`,
  `optimafront` on the Sail default `80`) and know nothing about this repo.

**Conclusion: `optimaback`/`optimafront` are unrelated, currently-live sibling
products from the same company, not consumers of this repo's API.** They
happen to confirm the org is `Optima Retail` and that this team is
comfortable building real Sanctum-token APIs when one is genuinely needed
(`optimaback` already has one, for its own reporting integration) — so the
pattern in `laravel_optima` is not unfamiliar or accidental, but it is a
separate, unconnected build.

### Why `laravel_optima` most likely exists — and what that implies about "API-first"

`docs/migration/legacy-master-data-mapping.md` (already in this repo) is the
strongest signal of intent: it's a from-scratch **rewrite** spec, describing
how "FileMaker / V2 Optima" tables (`empresas`, `clientes`, `proveedores`,
`tecnicos`, `workplaces`, `establecimientos`, `delegaciones` — Spanish legacy
naming) collapse into `laravel_optima`'s new schema (`companies`,
`company_relationships`, `establishments`, `delegations`). Combined with the
huge `~/back.sql` / `~/back-clean.sql` dumps (5.2 GB each) sitting in the
home directory, this reads as: **`laravel_optima` is a green-field successor
application, being built to eventually replace the "V2" system** (which
`optimaback`/`optimafront` are the current, live incarnation of, or a close
relative of it — the "V2" label in the migration doc and this new app's own
`/api/v3` numbering line up too neatly to be coincidental).

That reframes the "API-first" documentation: it most plausibly reflects
**forward-looking intent for the new "V3" product** — the same team that
gave `optimaback` its own token-authenticated API for a real external
consumer (Looker Studio) may be scaffolding the same capability into its
successor before any concrete client exists yet, rather than describing
something already wired to a real caller today. I can't confirm authorship
or timeline intent beyond what's written (this repo has no `.git` history to
inspect), but there is no evidence of an abandoned plan either — the
Sanctum/token code is recent (migration dated 4 days before this audit) and
fully test-covered where it matters most (login, companies, scoping).

### Straight answer to your three options

1. **The API is genuinely used externally → KEEP.** Not supported by
   evidence — nothing in this repo, and neither sibling repo, calls
   `/api/v3`.
2. **Not currently used but intentionally planned as a public API → tell me
   the cost/benefit.** This is the best-supported reading. Cost of keeping
   it as-is: 7 controllers/4 resources/3 requests/~590 lines of routes+support
   code, 2 test files with real gaps (no coverage for `Config\UserController`'s
   full CRUD or `logout`/`logout-all`), and an ongoing obligation to keep it
   in sync whenever `CompanyService`/`CompanyRelationshipService`/
   `EstablishmentService`/`UserService` change shape (low, since it's thin
   and already reuses Web Form Requests for 3 of 4 resources). Benefit: zero
   rework later if/when a mobile app or partner integration does show up,
   and it already mirrors a pattern (`optimaback`'s reporting API) this team
   has shipped for real before.
3. **Neither used nor planned → REMOVE.** Not supported — the documentation
   is current, specific, and consistent across three separate places
   (architecture doc, frontend doc, live dashboard copy), which is a lot of
   deliberate effort for something meant to be thrown away.

### My recommendation

**KEEP, but isolate it for future external consumers** — i.e. leave
`Api/V3` exactly where it is architecturally (it's already isolated: its own
namespace, its own routes file, its own Sanctum guard, zero business logic
of its own), and treat it as a distinct, intentionally-parallel surface
rather than something this refactor should touch. Concretely, if you agree:

- No deletions from §12. This refactor becomes scoped to genuine
  duplication only — and this audit found none beyond what's already
  documented as intentional.
- Two small, low-risk hygiene follow-ups worth doing regardless (listed as
  "Potential Follow-up," not part of this refactor unless you ask):
  add test coverage for `Api\V3\Config\UserController` and `logout`/
  `logout-all`; consider renaming the shared Form Requests out of the
  `Web\Companies\*` namespace (e.g. to `Http\Requests\Companies\*`) since
  they're already consumed by both surfaces and the `Web` label is
  slightly misleading.
- If at any point a concrete external consumer is confirmed (a mobile repo
  gets created, a partner signs on), `Api/V3` is already in the right shape
  to hand to that team as-is.

This is a recommendation, not an action — I have not implemented anything.
Tell me which of the three you want (KEEP / KEEP-but-isolate / REMOVE), and
whether you still want a Phase 2 plan for anything else in the original
brief (e.g. the minor Form Request namespace cleanup) even if `Api/V3`
itself is left alone.
