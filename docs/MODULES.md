# Modules & feature flags

Toggle optional kit capabilities via **Settings → System Control** (runtime), or `config/kit.php` / `.env` (deploy defaults).

| Flag / env | Default | What it enables |
|------------|---------|-----------------|
| `seo` | on | `Seo` helper, `SeoHead`, `/robots.txt`, `/sitemap.xml` |
| `KIT_API` / `api` | off | Sanctum token login, `/api/auth/*`, `/api/users` |
| `audit` | on | Auth logs UI + Spatie activitylog on `User` |
| `attachments` | on | `HasAttachments` + download routes |
| `translations_ui` | on | Settings → Translations JSON editor |
| `exports` | on | `ExportsSpreadsheet` / CSV + print views (users example) |
| `KIT_EXCEL_EXPORTS` / `excel_exports` | on | Excel-compatible SpreadsheetML via `?format=xlsx` (requires `exports`) |
| `tours` | on | `driver.js` admin shell tour |
| `multilingual` | on | Language switcher + locale cookie changes |
| Registration / Forgot password / Email verification | on | Fortify auth features (System Control only) |
| `KIT_OFFLINE` / `offline` | off | PWA + Dexie outbox + sync API — scaffold with `kit:scaffold Name --offline` so new resources inherit forms/list/sync automatically; see `OFFLINE.md` |
| `KIT_AI` / `ai` | off | AI writing assist on opted-in fields — see `AI.md` |
| `KIT_GOOGLE_AUTH` / `google_auth` | off | Google OAuth on login/register — toggle + credentials under **Settings → Google Auth** (`.env` values remain a fallback) |
| `mail_alerts` | on | Mail channel on `CriticalAlertNotification` (e.g. backup fail) |

Runtime overrides are stored in `org_settings` (`system.*` keys) via **Settings → System Control**. AI, Offline, and Google Auth keep their dedicated settings pages.

## Demo model without UI

`field_reports` stays in the catalog for offline sync, AI CRUD, attachments, and trash — but the kit does not ship Field Reports list/create/edit pages. Scaffold your own module or use `field_notes` as the visible CRUD example.

## Adding a domain module

1. Add a key under `config/mis_permissions.php` → `modules`.
2. `php artisan mis:sync-permissions`
3. Optionally `php artisan kit:scaffold YourResource`
4. Wire routes + sidebar; gate with `<Can>` / `usePermissions`.

## Trash / recycle bin

SoftDeletes is required for all deletable domain records. Deletes move items to **Settings → Trash**; restore or permanently delete from there. Items older than `TRASH_RETENTION_DAYS` (default **60**) are purged by the scheduled `trash:prune` job. Register new models in `config/trash.php` (scaffold does this automatically). Permissions: `trash.view`, `trash.restore`, `trash.delete`.

## Intentionally not in core

Full multi-tenant SaaS, carrier SMS, Filament, CRDT offline sync — extend in the clone.
