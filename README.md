# Laravel Optima

Inertia + React Laravel application.

- **UI:** React 19 + Inertia + TypeScript + Tailwind
- **Auth:** Session + Spatie Permission

## Setup

```bash
cd laravel_optima
composer install
npm install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Dev with Vite HMR:

```bash
npm run dev:all
# or in two terminals:
php artisan serve
npm run dev
```

## Seeded admin

- Email: `admin@optima.test`
- Password: `password`

## Smoke checks

```bash
# UI
open http://127.0.0.1:8000/login
```

## Docs

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — backend structure
- [docs/FRONTEND.md](docs/FRONTEND.md) — React / Inertia structure

## Tests

```bash
php artisan test
```
