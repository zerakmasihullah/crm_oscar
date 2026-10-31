# Cloning & customizing the kit

Short guide for turning this starter into a new MIS/ERP app.

## 1. Clone and boot

```bash
cp -R kit my-app && cd my-app
cp .env.example .env
# Set APP_NAME, APP_URL, APP_LOCALE, and DB_* (use a dedicated database name)
composer install
php artisan key:generate
# Optional interactive setup:
# php artisan kit:install
npm install
php artisan migrate --seed
npm run build
php artisan serve
```

Default login after seed: `admin@example.com` / `password` (Owner).

## 2. Branding knobs

| Knob | Where |
|------|--------|
| App name / default locale | Settings → General, or `org_settings` |
| Theme colors | Settings → Appearance (org-persisted) or `resources/css/app.css` |
| Admin UI patterns | Follow **global-security** (`MisPage`, `RowActionsMenu`, Card indexes) — not madrasa/Najm |
| Feature flags | `config/kit.php` + `.env` — see [MODULES.md](MODULES.md) |
| Locales | `config/locale.php` + `lang/{en,fa,ps}.json` |
| Permissions modules | `config/mis_permissions.php` then `php artisan mis:sync-permissions` |
| Force admin 2FA | `REQUIRE_ADMIN_TWO_FACTOR=true` |

## 3. Add a domain module

1. Add a module key under `config/mis_permissions.php`.
2. Run `php artisan mis:sync-permissions` (and re-seed roles if needed).
3. Optionally scaffold CRUD: `php artisan kit:scaffold YourResource`.
4. Wire routes + sidebar nav; gate UI with `<Can>` / `usePermissions`.

## 4. Optional helpers already in the kit

- **Login / system logs** — Settings (`audit.view`)
- **Translations editor** — Settings → Translations (`KIT_TRANSLATIONS_UI`)
- **Attachments** — `HasAttachments` + `/attachments/{id}/download`
- **SEO** — `App\Support\Seo` + `SeoHead.vue`; `/robots.txt`, `/sitemap.xml`
- **Product tour** — Dashboard “Take a quick tour”
- **Session revoke** — Security → Sign out other sessions
- **CSV export** — `ExportsCsv` trait (users example: `/settings/users/export`)
- **API** — `KIT_API=true` then `POST /api/auth/login`
- **Component gallery** — `/dev/gallery` (Owner)
- **Backups** — see [BACKUPS.md](BACKUPS.md)

## 5. Do not put domain into the kit

Keep courses, finance, HR, full SaaS tenancy, carrier SMS, etc. in the clone. Upgrade the kit by merging infrastructure changes, not by forking domain into core.
