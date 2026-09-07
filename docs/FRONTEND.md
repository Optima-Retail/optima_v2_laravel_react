# Frontend (Inertia + React)

## Stack

- Inertia.js + React 19 + TypeScript
- Tailwind CSS v4
- Lucide React (icons)
- Zustand (stores)
- Vite 8

## Style rules

- Brand color is friendly blue (`#2563eb`), not green/teal.
- Do not use CSS gradients.
- Do not use `translateY` / hover lift transforms (`hover:-translate-y-*`, `animate-rise`).
- Prefer `transition-colors` over motion that moves elements.

## Layout

```text
resources/js/
  app.tsx
  types/                  # Shared Inertia page props
  support/                # cn(), pagination types, domain types
  helpers/                # Pure helpers (arrays, confirm, pagination)
  services/
    config/               # Config domain Inertia/router wrappers
  stores/
    config/               # Config domain Zustand stores
  hooks/                  # useAuth, permission helpers
  layouts/
  components/
  pages/
    Config/               # Configuration UI (Users, Roles, …)
```

## How to grow

1. Put HTTP/navigation calls in `services/` (nest by domain, e.g. `services/config/`).
2. Put client state in `stores/` (Zustand; nest by domain, e.g. `stores/config/`).
3. Put pure functions in `helpers/`.
4. Put shared types/utilities in `support/`.
5. Keep pages thin: compose UI + call services/stores.

## Web routes

| Path | Page | Permission |
|------|------|------------|
| `/` | Auth/Login | guest |
| `/dashboard` | Dashboard/Index | auth |
| `/config/users` | Config/Users/Index | `users.view` |
| `/config/users/create` | Config/Users/Create | `users.create` |
| `/config/users/{id}/edit` | Config/Users/Edit | `users.update` |
| `/config/roles` | Config/Roles/Index | `roles.view` |
| `/config/roles/create` | Config/Roles/Create | `roles.create` |
| `/config/roles/{id}/edit` | Config/Roles/Edit | `roles.update` |
| `/config/work-order-types` | Config/WorkOrderTypes/Index | `work_order_types.view` |
| `/config/work-order-types/create` | Config/WorkOrderTypes/Create | `work_order_types.create` |
| `/config/work-order-types/{id}/edit` | Config/WorkOrderTypes/Edit | `work_order_types.update` |

Permissions are auto-imported from policy methods via `permissions:sync-from-policies`.

UI copy is English for now. Public registration is disabled; users are created by admins.
