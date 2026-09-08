# Field Help / Contextual Assistance

Database-driven help text shown next to form labels. UI chrome (labels, buttons) stays in React i18n; help copy lives in the database so editors can change it without shipping frontend builds.

## Naming convention

Use stable semantic keys:

```text
{domain}.{field}
```

Examples: `companies.tax_id`, `customers.credit_limit`, `invoices.tax_rate`.

Do **not** key help by translated labels, route names, React component names, or database IDs.

Optional DX constants live in `resources/js/config/fieldHelpKeys.ts`. The backend `field_helps.key` column remains the source of truth.

## Database

| Table | Purpose |
|---|---|
| `field_helps` | Definition: `key` (unique), optional `context`, `is_active`, `sort_order` |
| `field_help_translations` | Per-locale `title`, `description`, `example` — unique `(field_help_id, locale)` |

Content is **global/system-level** (not tenant-scoped) in this version.

## Locale fallback

1. Requested locale (`?locale=` or app locale from session / user / cookie via `SetLocale`)
2. If that translation is missing → `config('app.fallback_locale')` (normalized through `App\Support\Locale`)
3. If still missing → key omitted from the response (no empty tooltip)

Supported locales: `en`, `es` (`App\Support\Locale::SUPPORTED`).

## API

Authenticated session endpoint (same pattern as other JSON web routes — not `/api/v3`):

```http
GET /field-help?keys[]=companies.tax_id&keys[]=companies.legal_name&locale=es
```

Response:

```json
{
  "data": {
    "companies.tax_id": {
      "title": "NIF / CIF",
      "description": "Identificación fiscal de la empresa...",
      "example": "B12345678"
    }
  },
  "locale": "es"
}
```

Inactive definitions and unknown keys are omitted. Max 100 keys per request.

## Caching

`FieldHelpService` caches the full active catalog per locale as `field-help:locale:{locale}` (1 hour). Cache is cleared on create / update / delete (including translation sync).

## React usage

Preferred: wrap the form once. Every `Field` / standalone `Toggle` then resolves `{table}.{htmlFor|name}` automatically. New DB rows (e.g. `companies.name`) show the icon with no further frontend change.

```tsx
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Field } from '@/components/ui/Field';

<FieldHelpScope table="companies">
  <form>
    <Field label={t('common.name')} htmlFor="name">
      <Input id="name" ... />
    </Field>
    {/* → requests companies.name */}

    <Toggle name="is_active" checked={...} onCheckedChange={...} />
    {/* → requests companies.is_active */}
  </form>
</FieldHelpScope>
```

Overrides when the DOM id does not match the semantic key:

```tsx
<Field htmlFor="related_company_tax_id" helpField="companies.tax_id" ... />
<Field htmlFor="assign_user_id" helpField={false} ... />  {/* disable */}
```

Standalone `<FieldHelp field="companies.tax_id" />` still works outside `Field`.

`FieldHelpProvider` (in `AppLayout`) syncs the Zustand store to the current locale. Multiple `FieldHelp` instances batch missing keys (~16ms debounce). Missing help → **nothing** rendered (no empty icon).

## Seeding

`FieldHelpSeeder` upserts curated EN/ES definitions for non-obvious fields. Re-run anytime:

```bash
./vendor/bin/sail artisan db:seed --class=FieldHelpSeeder
```

Editors can also add keys via Config → Field help; scoped forms pick them up automatically when the key matches `{table}.{column}`.

## Adding help for a new field

1. Create a `field_helps` row with key `{table}.{column}` (same as form `htmlFor` / Toggle `name`) and translations.
2. Ensure the form is wrapped in `<FieldHelpScope table="…">` (already done for domain forms).
3. No React prop changes required when the column id matches the key.

## Admin CRUD

Config UI at `/config/field-helps` (permissions `field_helps.*`).

Create/edit flow:

1. Select a **table** from live DB schema.
2. Select a **column** for that table.
3. Help key is built as `{table}.{column}`.
4. Enter `title` / `description` / `example` for each supported locale (`en`, `es`).

System tables (migrations, cache, jobs, sessions, secrets columns) are excluded from the selectors.

`FieldHelpService::create|update|delete` and `FieldHelpPolicy` back the UI. Runtime resolve stays **auth-only** so any logged-in user can load help on forms.
