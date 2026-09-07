# Architecture

## Goals

`laravel_optima` is an **Inertia + React** Laravel application. The UI is the
only HTTP surface: session auth, Inertia pages, and JSON data endpoints used by
the React tables (`/…/data`).

## Stack

- Laravel 13
- Laravel Sail (Docker) + MySQL 8.4
- Spatie Laravel Permission (roles & permissions)
- Inertia.js + React 19 + TypeScript + Tailwind + Lucide (web UI)

## Local Docker

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev   # or npm run build on the host
```

App: `http://localhost:8088` · phpMyAdmin: `http://localhost:8089` · host MySQL port `3308` · Compose DB host: `mysql` (user `sail` / password `password`).

Ports default to free values (`APP_PORT=8088`, `FORWARD_DB_PORT=3308`, `FORWARD_PHPMYADMIN_PORT=8089`, `VITE_PORT=5175`) so they do not collide with other local Sail stacks.

## Directory layout

```text
app/
  Domain/
    Config/
      Users/Services/     # UserService
      Roles/Services/     # RoleService
    Auth/                 # enums, permission discovery/sync
  Http/
    Controllers/Web/
      Auth/
      Companies/
      Config/             # Inertia controllers under /config/*
    Middleware/HandleInertiaRequests.php
  Models/
  Policies/
  Support/
routes/
  web.php                 # Inertia pages (`/config/*`)
resources/js/             # See docs/FRONTEND.md
```

Controllers stay thin and delegate create/update/delete/list logic to domain services.

Domain records (`users`, `roles`, `permissions`) use **soft deletes** only. Deletes go through
domain services (`UserService`, `RoleService`, `PolicyPermissionSync`) and never hard-delete rows.
Unique emails/role names are released on soft delete so they can be reused.

## Auth

| Surface | Mechanism |
|---------|-----------|
| Web UI (`/`, `/dashboard`, `/config/*`) | Session + Inertia |

Public registration is disabled. Users are created by admins via Configuration → Users CRUD.

Middleware aliases: `auth`, `guest`, `role`, `permission`, `role_or_permission`.

## Default seed

- Roles: `admin`, `user`
- Permissions: discovered from `App\Policies\*Policy` methods (`users.*`, `roles.*`, …)
- Sync with `php artisan permissions:sync-from-policies`
- Admin: `admin@optima.test` / `password`
