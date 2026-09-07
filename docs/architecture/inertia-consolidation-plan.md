# Inertia Consolidation — Phase 2 Plan

Follows `docs/architecture/inertia-consolidation-audit.md`. Per your decision:

- `Api/V3` (controllers, resources, requests, routes, tests) is **kept, isolated, and out of
  scope** — nothing below touches any file under `app/Http/Controllers/Api`,
  `app/Http/Resources/Api`, `app/Http/Requests/Api`, `routes/api*`, or `tests/Feature/Api`.
- No database changes. No business-logic changes. No behavior changes to any Domain
  service.
- This is a plan only. Nothing has been implemented. I will not start Phase 3 until you
  approve item-by-item or as a whole.

Verified baseline for this plan: `npx tsc --noEmit` clean, `vendor/bin/pint --test` clean
(one pre-existing, unrelated finding in `bootstrap/app.php`), full test suite 79 passed /
874 assertions.

---

## Summary table

| # | Change | Risk | Behavior change | Recommendation |
|---|---|---|---|---|
| 1 | Remove dead `Relationships` frontend cluster | Low | None | **Do it** |
| 2 | Split `routes/web.php` into per-domain files | Low–Medium | None | **Do it** |
| 3 | Rename shared Form Requests out of `Web\Companies\*` | — | — | **Not doing** (would require touching `Api/V3` imports) |
| 4 | Extract shared Tabulator controller boilerplate | — | — | **Considered, rejected** |
| 5 | Merge `CompanyMemberController` into `CompanyController` | — | — | **Considered, rejected** |
| 6 | Rename `Controllers/Web/*` to drop the `Web` segment | — | — | **Not doing** (Api/V3 makes the distinction meaningful again) |
| 7 | Unused imports (PHP + TS) | — | — | **None found** — already clean |
| 8 | `docs/FRONTEND.md` — document `support/types/` alongside `types/` | Low | None (docs only) | **Do it** |

Items 3–7 are "no code change" entries — they're in the plan because you asked me to
evaluate them, and the answer is informative even where the action is "leave alone."
Only items 1, 2, and 8 propose an actual diff.

---

## 1. Remove dead `Relationships` frontend cluster

**Current state:** Three orphaned artifacts, confirmed unreferenced by anything:
- `resources/js/pages/Relationships/` — empty directory, no files inside, no route/controller
  renders `'Relationships/...'` anywhere.
- `resources/js/stores/relationshipsStore.ts` — a Zustand filters store
  (`search/kind/sort/direction/per_page`), same shape as the old per-page stores removed
  earlier this session for Clients/Suppliers. Not imported anywhere.
- `resources/js/services/relationships.ts` (`relationshipsService`) — re-exported from
  `resources/js/services/index.ts` but never imported by any page/component.

**Proposed state:** Delete the empty directory and the two files; remove the
`relationshipsService` export line from `resources/js/services/index.ts`.

**Why beneficial:** This is exactly the "dead code" category the brief asks to clean up.
It's leftover scaffolding from before Clients/Suppliers were split into their own
pages/services/stores (which is the pattern actually in use today) — keeping it around
invites a future contributor to import the wrong (dead) service by mistake.

**Files affected:**
- Delete: `resources/js/pages/Relationships/` (empty), `resources/js/stores/relationshipsStore.ts`, `resources/js/services/relationships.ts`
- Edit: `resources/js/services/index.ts` (remove one export line)

**Risk:** Low — confirmed zero references via repo-wide grep before proposing this.

**Behavior change:** None.

---

## 2. Split `routes/web.php` into per-domain files

**Current state:** `routes/web.php` is 385 lines, one flat file covering guest/auth,
dashboard, companies + members + relationships (clients/suppliers) + establishments,
brands, and 14 `config/*` resources. Meanwhile `routes/api.php`/`routes/api/v3.php`
already split by domain (`routes/api/v3/companies.php`, `routes/api/v3/config/users.php`),
per `routes/api.php`'s own comment: *"Keep version files under routes/api/."* There's no
equivalent convention documented or applied for `web.php`.

**Proposed state:** Split into:
```
routes/web.php                    — requires the files below, keeps top-level
                                     guest/auth + dashboard/logout/locale routes inline
                                     (they're small and don't belong to a bigger domain)
routes/web/companies.php          — companies CRUD+data, company members, switch-company
routes/web/relationships.php      — clients + suppliers (CompanyRelationshipController)
routes/web/establishments.php     — establishments CRUD+data
routes/web/brands.php             — brands CRUD + messages
routes/web/config.php             — all 14 config/* resource groups (kept as one file,
                                     not 14 — see rationale below)
```
Every route name, URI, and middleware stays byte-for-byte identical — this is purely
"which file defines it," verified via `php artisan route:list` diff before/after.

**Why beneficial:** Matches the multi-file convention this repo already established for
the API tree, makes `web.php` scannable instead of a 385-line scroll, and gives each
domain its own file boundary as more resources get added (this file has grown ~2.5x
already just from the Tabulator migration earlier this session).

**Why one `config.php`, not 14 files:** The API side only split at the *domain*
granularity (`companies.php`, `config/users.php`) — not one file per resource. The 14
config resources are each a 4–6 line block; one file of ~90 lines is more scannable than
14 files you have to open individually to see the whole config surface at a glance.
Splitting further would be organizing for its own sake.

**Files affected:** `routes/web.php` (trimmed to requires + the small inline groups), 5
new files under `routes/web/`.

**Risk:** Low–medium. Mechanical, but must be done carefully: `Route::prefix('config')->name('config.')->group(...)` wraps the whole config block today, so `routes/web/config.php` must be `require`d *inside* that same wrapping group in `web.php` (not just `require`d flat), or every config route name/URI would silently change. I'll verify with a full `route:list` diff and the full test suite before considering this done.

**Behavior change:** None (verified by route-list diff + full test suite, not just by inspection).

---

## 3. Form Request namespace (`Web\Companies\*` shared with Api/V3) — not doing

**Current state:** `StoreCompanyRequest`, `UpdateCompanyRequest`,
`Store/UpdateCompanyRelationshipRequest`, `Store/UpdateEstablishmentRequest`, and
`SwitchCompanyRequest` live under `App\Http\Requests\Web\Companies\*` but are imported
directly by `Api\V3\CompanyController`, `Api\V3\CompanyRelationshipController`,
`Api\V3\EstablishmentController`, and `Api\V3\MeCompanyController`. The `Web` segment in
that namespace is misleading, since these requests are genuinely shared.

**Why I'm not proposing a change:** Moving them to a neutral namespace (e.g.
`App\Http\Requests\Companies\*`) is a legitimate cosmetic improvement, but it requires
updating the `use` statements in four `Api\V3` controller files — and you've asked me not
to modify anything under `Api/V3`. I'd rather flag this honestly as "found, understood,
left alone" than quietly reinterpret "don't touch Api/V3" to mean "except for import
lines." If you ever revisit the API layer for another reason, this is a one-line-per-file
change to fold in at that time.

**Files that would be affected if you ever say yes:** 5 files to move/rename under
`app/Http/Requests/`, plus a `use` update in 3 Web controllers and 4 Api/V3 controllers.

**Risk / behavior change:** N/A — no action proposed.

---

## 4. Shared Tabulator controller boilerplate — considered, rejected

**Current state:** All 16 migrated list pages' controllers (Companies, Currencies,
Timezones, Teams, Languages, WorkOrderTypes, Banks, Brands, Countries, Delegations,
Integrations, RatingTypes, Roles, Series, Users, Establishments, Clients/Suppliers) have a
`data()` method of the same shape:
```php
public function data(Request $request): JsonResponse
{
    $this->authorize('viewAny', X::class);
    $filters = TabulatorQuery::fromRequest($request, allowedSorts: [...], ...);
    return TabulatorResponse::fromPaginator($this->x->paginateForWeb($filters));
}
```
This is 5–8 lines, differing per controller only in the model class, allowed sort
columns, filter keys, and the service call.

**Why I'm not proposing an extraction:** Wrapping this in a shared trait/base-controller
method would need a callable or a generic resource/paginator abstraction to stay
type-safe, which trades ~4 lines of duplication per controller for an extra layer of
indirection that obscures each controller's own `authorize()` target and paginate call at
a glance. Your own brief is explicit here: *"Do not introduce unnecessary abstractions
purely for abstraction's sake"* and *"Do not introduce unnecessary Services/Actions if the
existing code is already simple and clean."* Each `data()` method is already about as
simple as it can be and reads correctly in isolation — I'd rather leave 16 obvious
5-line methods than add one clever helper that saves a few lines per file at the cost of
directness.

**Files affected / risk / behavior change:** N/A — no action proposed.

---

## 5. Merge `CompanyMemberController` into `CompanyController` — considered, rejected

**Current state:** `Web\Companies\CompanyMemberController` (2 methods: `store`/`destroy`
for attaching/detaching a user to/from a company) is a separate class from
`Web\Companies\CompanyController`.

**Why I'm not proposing a merge:** This is a single-purpose, invokable-adjacent controller
for a clearly bounded sub-resource (company membership), which is *more* aligned with
"keep controllers thin," not less. Folding it into `CompanyController` would grow that
controller without making anything clearer — it would just move two methods into a
bigger file. Not a duplication problem; nothing to consolidate.

**Files affected / risk / behavior change:** N/A — no action proposed.

---

## 6. Rename `Controllers/Web/*` to drop the `Web` segment — not doing

**Current state:** All Inertia controllers live under `App\Http\Controllers\Web\*`. The
original brief suggested flattening this (`Controllers/CompanyController.php` instead of
`Controllers/Web/CompanyController.php`) "if there is no longer a meaningful distinction
between HTTP surfaces."

**Why I'm not proposing this:** That suggestion was conditioned on removing `Api/V3`
entirely. Since you've decided to keep `Api/V3` as a distinct, isolated surface, the `Web`
segment is exactly the meaningful distinction the brief was asking about — it's what
tells a reader "this controller renders Inertia pages, not JSON," which matters more, not
less, now that both surfaces coexist deliberately. Renaming would remove a signal that's
now confirmed to be load-bearing.

**Files affected / risk / behavior change:** N/A — no action proposed.

---

## 7. Unused imports/namespaces — none found

Checked mechanically rather than by inspection:
- **PHP:** Pint's default ("laravel") preset includes the `no_unused_imports` fixer.
  `vendor/bin/pint --test --format agent` across `app/`, `routes/`, `tests/` reports
  exactly one finding, in `bootstrap/app.php`, unrelated to imports and pre-existing
  (untouched by any work this session).
- **TypeScript:** `tsconfig.json` has `noUnusedLocals: true` and `noUnusedParameters:
  true`. `npx tsc --noEmit -p tsconfig.json` across all of `resources/js` returns clean.

Both checks are configured to fail loudly on unused imports/locals, and both pass. No
action proposed.

---

## 8. `docs/FRONTEND.md` — document `resources/js/types/` vs `resources/js/support/types/`

**Current state:** `docs/FRONTEND.md`'s directory diagram lists `types/` (shared Inertia
page props) but doesn't mention `support/types/domain.ts` (domain entity list-item types
like `CompanyListItem`, `BankListItem`, used by every `RemoteDataTable` page). The two
aren't duplicates — they serve different, non-overlapping purposes — but the doc doesn't
say so, which could read as an inconsistency to a new contributor.

**Proposed state:** Add one line to the `support/` entry in `docs/FRONTEND.md`'s layout
diagram, e.g. `support/types/ # Domain entity types (CompanyListItem, …), distinct from
top-level types/ (shared Inertia page props)`.

**Why beneficial:** Cheap, removes a real (if minor) point of confusion, matches your
"naming/structure improvements" ask.

**Files affected:** `docs/FRONTEND.md` only.

**Risk / behavior change:** None — documentation only.

---

## What I did *not* find

- No other duplicate/redundant Web controllers, Form Requests, or Domain services beyond
  what's listed above.
- No orphaned Policies, no orphaned migrations, no dead middleware.
- No frontend code calling `/api/*` anywhere (confirmed in the audit; re-confirmed here —
  nothing changed since).
- No Domain service that's a thin pass-through with no logic worth keeping — `ListQuery`,
  `TabulatorQuery`, `TabulatorResponse`, `ActiveCompany`, and `ResolvesActiveCompany` all
  carry real, non-trivial, multiply-reused logic and are not candidates for removal or
  further simplification.

## Next step

Waiting for your go-ahead. You can approve all of §1/§2/§8 together, pick a subset, or
ask me to adjust anything above before I touch a single file.
