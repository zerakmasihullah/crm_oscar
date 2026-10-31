# Yaqeen Afghanistan MIS/ERP Starter Kit — Planning Document

> **Status:** Phase 1–3 actionable kit scope complete (2026-07-24) — auth, RBAC, i18n/RTL (en/fa/ps), GS-aligned admin UI, settings, notifications, backups (tiers/mirror/docs), scaffold, login audit, admin 2FA gate, SEO, attachments, session revoke, tours, theme colors, activitylog, status logs, translations UI, CSV/print stubs, Sanctum API stub, digits/Jalali helpers, kit:install, demo seeder + component gallery, CI lint. Heavy SaaS tenancy / carrier SMS / Filament remain out of scope. See README + `docs/CLONING.md` + `docs/MODULES.md`.  
> **Workspace:** `/Users/sabawoonyaqubi/Desktop/Yaqeen/kit`  
> **Date:** 2026-07-24  
> **Goal:** A cloneable Laravel + Inertia/Vue starter that ships every-project infrastructure for Afghan organizations (auth, RBAC, i18n/RTL, admin shell, backups, settings, audits) — **not** domain content.  
> **Admin UI source of truth:** **`global-security`** (MIS page chrome, floating dark sidebar, settings layout, Card lists, `RowActionsMenu`). Do **not** use madrasa-website or Najm-Almadris for sidebar/tables/layouts.

---

## Executive summary

`kit` already contains a stock **Laravel Vue starter kit** (Laravel 13 + Vue 3 + Inertia 3 + Fortify + Tailwind 4 + shadcn-vue). It is the right base. The work is to **port mature, general infrastructure** from three production apps into this scaffold, then strip domain modules.

| Source of truth | Best for |
|-----------------|----------|
| **global-security** | **Admin UI chrome** (sidebar, MisPage, tables, row actions, settings layout), config-synced permission matrix, audit (activitylog + auth logs + status logs), soft deletes conventions, theme color settings, EnsureUserIsActive |
| **madrasa-website** | Auth (Fortify + 2FA + passkeys + admin 2FA gate), admin CRUD scaffolding (`admin_resources` + scaffold), SEO, security headers, Sanctum API patterns, 5-locale catalogs, CI — **not** sidebar/table visual design |
| **Najm-Almadris** | SMS gateway abstraction, tenancy patterns (optional plugin), session version revoke, lookup-table settings CRUD, Jalali/localized digits, Spatie Backup package UI reference — **not** Filament or admin chrome |
| **kit (current)** | Clean Laravel 13 Vue starter base — keep; build on top |

**Primary stack recommendation:** Laravel 13 + Inertia/Vue 3 + TypeScript + Tailwind 4 + shadcn-vue + Fortify + Spatie Permission (+ Activitylog) — aligned with madrasa/global-security, **not** Filament (Najm’s dual Filament+Vue stack is too heavy for a general starter).

---

## 1. Cross-project inventory

### 1.1 Tech stack comparison

| Layer | kit (base) | madrasa-website | global-security | Najm-Almadris |
|-------|------------|-----------------|-----------------|---------------|
| Laravel | 13.17 | 13.7 | 13.7 | **12** |
| Frontend | Vue 3 + Inertia 3 + Vite 8 + TW4 | Same family | Same family | Vue 3 + Inertia + Vite 6 + **TW3** + **Filament 3** |
| Auth | Fortify + passkeys (stock) | Fortify + Sanctum + passkeys + admin 2FA | Fortify + passkeys | Custom TOTP (Google2FA) + sessions |
| RBAC | None | Spatie Permission 6 | Spatie Permission 8 + config sync | **Custom** school RBAC |
| Audit | None | Absent | Spatie activitylog + auth logs | Login events + access audit |
| Soft deletes | Stock users only | Absent | Widespread | Widespread |
| Backups | None | Custom BackupService | Custom BackupService | Spatie laravel-backup |
| i18n | Stock EN | ps/fa/ar/ur/en | en/fa/ps | en/ps/fa/ur/ar |
| CI | tests.yml + dependabot | tests + lint | tests + lint | Local PHPUnit only |

**Verdict:** Build kit on Laravel 13 Vue starter (already present). Port patterns from madrasa + global-security first. Treat Najm as a source for SMS, tenancy (optional), and session hardening — not for Filament or custom RBAC as the default.

---

### 1.2 Feature-by-feature inventory

#### Auth (login, 2FA, password reset, sessions)

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Fortify + 2FA + passkeys UI | **madrasa** (tie with kit stock) | `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`, `resources/js/pages/auth/*`, `ManageTwoFactor.vue`, `ManagePasskeys.vue` | kit already has stock Fortify/passkeys — enhance with madrasa’s admin gate |
| Force admin 2FA | **madrasa** | `app/Http/Middleware/EnsureAdminTwoFactor.php`, `config/security.php` | Copy |
| Browser session revoke | **madrasa** | `app/Support/BrowserSessions.php` | Copy |
| Inactive user session kill | **global-security** | `EnsureUserIsActive.php`, `User::disable()/enable()` | Copy |
| Session version bump / revoke all | **Najm** | `UserSessionManager.php`, `EnsureValidSessionVersion.php` | Strong; Phase 2 |
| API auth + 2FA challenge | **madrasa** | `Api/AuthController`, `ApiTwoFactorChallenge.php`, Sanctum | Optional module |
| Custom TOTP stack | Najm | `TwoFactorAuthenticationService.php` | Skip — prefer Fortify |

**Copy from:** madrasa (primary) + global-security (active user) + Najm (session version, Phase 2).

---

#### Roles & permissions

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Config-driven `{module}.{verb}` matrix | **global-security** | `config/mis_permissions.php`, `MisPermissionRegistrar.php`, `php artisan mis:sync-permissions` | **Best pattern** for a starter |
| Spatie Permission package | madrasa / global-security | `config/permission.php`, migrations | Use Spatie 8 if PHP allows; else 6 |
| Frontend `<Can>` + `usePermissions` | **global-security** | `composables/usePermissions.ts`, `components/Can.vue` | Copy |
| Roles CRUD UI | global-security / madrasa | `pages/settings/Roles/*` or `pages/dashboard/roles/*` | Prefer settings layout from global-security |
| Admin permission registry (resource.action) | madrasa | `AdminPermissions.php`, `PermissionSeeder.php` | Merge idea into `mis_permissions` style |
| Custom school RBAC + PermissionMatrix | Najm | `config/school_permissions.php`, `PermissionMatrix.vue` | **Do not** make default; optional inspiration for matrix UI |

**Copy from:** global-security’s config-sync model (primary). Seed with kit-generic modules only (`users`, `roles`, `settings`, `backups`, `dashboard`).

---

#### Multi-language / i18n / RTL

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Locale registry with `dir` | **global-security** (structure) + **madrasa** (locale set) | `config/locale.php` vs `config/locales.php` | Kit ships **en, fa, ps** as primary |
| SetLocale middleware + cookie | both | `SetLocale.php`, LocaleController | Copy |
| Direction sync | madrasa / global-security | `useLocaleDirection.ts` / `useLocale.ts`, `LocaleDirectionSync.vue` / `LocaleSync.vue` | Copy |
| Shared Inertia translations | both | `HandleInertiaRequests` | Prefer caching pattern from Najm (`InertiaTranslationResolver`) for perf |
| In-app translation editor | **global-security** (cleaner) / madrasa | `TranslationFileService`, `pages/settings/Translations/` | Phase 2 |
| Language catalogs | madrasa (5 langs) | `lang/{en,fa,ps,ar,ur}.json` | **Strip domain keys**; keep shared UI keys as seed |
| Localized digits / Jalali | Najm | `LocalizedDigits.php`, `filament-jalali` | Optional Phase 3 |

**Afghanistan default:** `fa` (Dari), `ps` (Pashto), `en`. RTL for `fa`/`ps`/`ar`. Default locale: make configurable (`APP_LOCALE`); recommend `fa` or `ps` for Afghan deployments.

---

#### Settings / system config

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| User settings (profile, security, appearance) | **kit stock** + madrasa/global-security | `routes/settings.php`, settings layouts | Already in kit |
| Site/org settings (key-value cached) | madrasa | `SiteSetting.php`, `PublicSiteConfig.php` | Generalize to `OrgSetting` |
| Theme color admin | **global-security** | `theme-colors.ts`, `ThemeColorSettings.vue`, FOUC script in `app.blade.php` | Copy |
| Lookup table CRUD | **Najm** | `LookupTable.php`, `LookupTable.vue` | Strong for MIS reference data — Phase 2 |
| Settings shell nav | global-security | `layouts/settings/Layout.vue` | Copy |

---

#### User management

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Users CRUD + roles/perms | madrasa / global-security | `UserController` / `UserManagementController`, users Vue pages | Merge: soft-disable from GS + role fields from madrasa |
| Active/disable | **global-security** | `User::disable()/enable()` | Must-have |
| Seeders | both | `UserSeeder`, `RolesAndPermissionsSeeder` | Generic roles: `owner`, `admin`, `user` |

---

#### Backups

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Custom multi-layer backup | **madrasa** or **global-security** | `BackupService.php`, `config/backup.php`, UI Index | Both mature; pick **one** implementation and standardize |
| Spatie Backup package | Najm | `spatie/laravel-backup`, Filament/Vue UI wrappers | Alternative if you prefer package maintenance |
| Schedule + retention + download UI | all three that have it | Controllers + Vue pages | Phase 1: service + schedule + basic UI |

**Recommendation:** Port **madrasa’s** `BackupService` (DB dump + files zip + checksum + retention + optional S3) — proven and independent of Filament. Cross-check GS job queue pattern (`RunBackupJob`) for UX.

---

#### Product tours / onboarding

| Finding | All four projects |
|---------|-------------------|
| Product tours (driver.js / shepherd / intro.js) | **Absent everywhere** |

**Plan:** Greenfield in Phase 3 — e.g. `driver.js` + JSON tour definitions per route + “dismissed tours” user preference.

---

#### SEO / SeoHead

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Seo helper + SeoHead.vue | **madrasa** | `app/Support/Seo.php`, `components/public/SeoHead.vue`, `lib/seo.ts` | Core kit optional — for apps with public pages |
| Sitemap / robots | madrasa | `SitemapController`, `RobotsController` | Optional plugin |
| Private MIS | global-security / Najm | Title only | Default for ERP-only clones |

---

#### Notifications

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Permission-scoped in-app notifications | **global-security** / madrasa | `NotificationService.php` / `AdminNotificationService.php`, `NotificationBell.vue` | Copy GS DTO + audience pattern |
| Flash toasts | both | `vue-sonner`, `flashToast.ts` | Already partially in kit |
| Mail channel for alerts | thin everywhere | Fortify mail only | Phase 2: mail channel option |

---

#### Activity logs / audit

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Spatie activitylog on models | **global-security** | `LogsActivity` on key models | Phase 2 core |
| Authentication log (IP/UA fingerprint) | **global-security** | `AuthenticationLog*`, `LogAuthenticationActivity` | Phase 1 or 2 — high value for Afghan MIS |
| Status change morph log | **global-security** | `HasStatusChangeLogs`, `StatusChangeHistory.vue` | Phase 2 |
| Login events | Najm | `LoginEvent*` | Overlaps GS auth logs — pick GS |

**madrasa gap:** no real audit trail — do not copy its “activity feed” widget as audit.

---

#### File uploads / media

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| Polymorphic attachments | **global-security** | `Attachment.php`, `HasAttachments.php`, `EntityAttachments.vue` | Best general pattern |
| Simple CRUD image upload | madrasa | `AdminImageUpload.vue`, `ManagesAdminResource` upload | Fine for resource forms |
| Tenant-scoped storage | Najm | `TenantStorage.php` | Optional multi-tenant plugin |
| Spatie Media Library | nowhere | — | Not required for MVP |

---

#### Design system & admin shell

| Piece | Best source | Paths | Notes |
|-------|-------------|-------|-------|
| shadcn-vue primitives | kit / madrasa / GS | `resources/js/components/ui/*` | Already in kit |
| App sidebar shell | madrasa / GS | `AppShell.vue`, `AppSidebar.vue`, `AppSidebarLayout.vue`, `NavMain.vue` | Align with kit stock; enhance from GS/madrasa |
| MIS page chrome | **global-security** | `MisPage.vue`, `MisTabs.vue`, `MisPagination.vue`, `RowActionsMenu.vue`, `useMisPage.ts` | Excellent CRUD UX |
| Config-driven admin resources | **madrasa** | `config/admin_resources.php`, `ManagesAdminResource.php`, `admin:scaffold`, `AdminResource*` Vue | Excellent for rapid modules |
| Theme tokens / brand CSS | madrasa `app.css` + GS theme-colors | Rebrandable CSS variables | Neutral kit theme (not madrasa gold/emerald brand) |
| DashboardShell (Najm) | Najm | `DashboardShell.vue` | Alternate — skip Filament coupling |

**UI source of truth:** **global-security** for MIS chrome (pages, row actions, settings); **madrasa** for config-driven resource scaffolding + public/SEO components; **kit** for base shadcn/auth layouts.

---

#### API patterns

| Piece | Best source | Notes |
|-------|-------------|-------|
| Sanctum + admin API | **madrasa** `routes/api.php`, `Api/Admin/*` | Optional plugin — many MIS apps are Inertia-only |
| Inertia-only | global-security | Default for kit |
| Thin scanner API | Najm | Domain-specific — skip |

**Default:** Inertia monolith. Ship `routes/api.php` + Sanctum as optional (`kit.features.api`). Offline foundation (`kit.features.offline`) extends the same API with `/api/sync/*` — see `docs/OFFLINE.md`.

---

#### Security headers / middleware

| Piece | Best source | Paths |
|-------|-------------|-------|
| SecurityHeaders (CSP, HSTS, etc.) | **madrasa** / **Najm** | `SecurityHeaders.php` |
| EnsureAdminRole / permission middleware | madrasa / GS | Spatie aliases in `bootstrap/app.php` |
| Slow request logging | Najm | `LogSlowRequests.php` — Phase 3 |

**global-security gap:** no dedicated security-headers middleware — copy from madrasa.

---

#### Email / SMS / communications

| Piece | Best source | Notes |
|-------|-------------|-------|
| Laravel mail + Fortify | kit / all | Core |
| SMS gateway abstraction | **Najm** | `config/sms.php`, `Services/Sms/*`, `FakeSmsGateway` — **optional plugin** (very relevant for Afghanistan) |
| WhatsApp deep links | madrasa | Not transactional SMS — skip as infra |

---

#### Themes / RTL

Covered under i18n + design system. Kit must set `dir`/`lang` on `<html>` before paint (see GS `app.blade.php` FOUC prevention + LocaleSync).

---

#### Exports / reports scaffolding

| Finding | Notes |
|---------|-------|
| madrasa | Export **lang keys only** — no real export |
| global-security | Print views (attendance/payroll) |
| Najm | Maatwebsite Excel + Filament export + mPDF |

**Plan:** Phase 2 — thin CSV export trait + optional Excel package; print layout stub. Do not pull Filament export stack.

---

#### Soft deletes & CRUD conventions

| Piece | Best source / notes |
|-------|---------------------|
| SoftDeletes as default on business models | **global-security** / Najm; kit via `kit:scaffold` |
| Config-driven CRUD scaffold | **madrasa** (`admin:scaffold`) / kit `kit:scaffold` |
| Admin CRUD online path | Inertia `useForm` |
| Flash toasts after mutations | GS / madrasa |

**Kit convention proposal:** Soft deletes ON for all domain models; hard-delete only via `forceDelete` permission. Scaffold command from madrasa adapted to kit modules.

---

#### Offline / PWA / sync

| Piece | Source | Notes |
|-------|--------|-------|
| PWA shell (`vite-plugin-pwa`) | greenfield | App-shell precache + offline fallback |
| Dexie outbox + `useOfflineForm` | greenfield | Client UUID, pending banner, flush on online |
| Sanctum `/api/sync/*` | greenfield | Idempotent batch + pull + metrics |
| `kit:scaffold --offline` | greenfield | Handler stub + client_uuid column |
| Docs | `docs/OFFLINE.md` | Opt-in checklist + browser matrix |

**Flag:** `kit.features.offline` / `KIT_OFFLINE=true`. Conflict policy v1 = LWW + idempotent `client_uuid`. No CRDTs in core.

---

#### Testing / CI

| Piece | Best source |
|-------|-------------|
| Dual workflows (tests + lint) | **madrasa** / **global-security** |
| Auth/settings feature tests | kit already has stock; expand from madrasa |
| Permission/locale/backup tests | global-security |
| Najm | Strong domain tests but **no GitHub Actions** — don’t follow that gap |

---

### 1.3 Best-version matrix (quick reference)

| Capability | Primary copy source | Secondary |
|------------|---------------------|-----------|
| Auth / 2FA / passkeys | madrasa-website | kit stock |
| Admin 2FA enforcement | madrasa-website | — |
| Permission matrix sync | **global-security** | madrasa AdminPermissions |
| Roles/users UI | global-security | madrasa |
| i18n + RTL | global-security (config/dir) + madrasa (locales + catalogs) | Najm (cache) |
| Translation editor | global-security | madrasa |
| Backups | madrasa BackupService | GS jobs/UI |
| Audit / auth logs | **global-security** | Najm login events |
| Soft deletes | global-security | Najm |
| Attachments | global-security | madrasa image upload |
| Admin CRUD scaffold | **madrasa** | GS MisPage patterns |
| MIS page UX | **global-security** | — |
| SEO | madrasa | — |
| Security headers | madrasa / Najm | — |
| Notifications bell | GS / madrasa | — |
| SMS | Najm (optional) | — |
| Tenancy | Najm (optional) | — |
| Tours | **None — greenfield** | — |
| CI | madrasa / GS | kit |

---

## 2. Recommended starter kit architecture

### 2.1 Tech stack (locked)

| Layer | Choice | Rationale |
|-------|--------|-----------|
| PHP | ^8.3 (prefer 8.4 in CI) | Matches kit + madrasa |
| Framework | Laravel 13 | Already in kit |
| SPA bridge | Inertia 3 + Vue 3 + TypeScript | Shared across Yaqeen apps |
| CSS | Tailwind 4 + CSS variables | kit / madrasa / GS |
| UI primitives | shadcn-vue (new-york-v4) + Reka UI + Lucide | Already in kit |
| Auth | Fortify + optional passkeys + 2FA | kit + madrasa |
| RBAC | `spatie/laravel-permission` + config registrar | GS pattern |
| Audit | `spatie/laravel-activitylog` | GS |
| API (optional) | Sanctum | madrasa |
| Tooling | Pint, Larastan, PHPUnit, ESLint, Prettier, Wayfinder | All three modern apps |
| **Not default** | Filament, custom RBAC, Spatie Media Library | Too heavy / divergent |

### 2.2 Proposed folder structure (additions on top of kit)

```
kit/
├── docs/
│   ├── STARTER_KIT_PLAN.md          # this file
│   ├── CLONING.md                   # how to clone & customize
│   ├── MODULES.md                   # module enable/disable guide
│   ├── BACKUPS.md                   # retention, mirror, restore
│   └── OFFLINE.md                   # offline / PWA foundation
├── app/
│   ├── Console/Commands/            # SyncPermissions, RunBackup, ScaffoldResource
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Settings/            # users, roles, translations, backups, logs
│   │   │   ├── Admin/               # dashboard + scaffolded resources
│   │   │   └── Api/                 # optional
│   │   ├── Middleware/              # SetLocale, SecurityHeaders, EnsureActive, EnsureAdmin2FA
│   │   └── Controllers/Concerns/    # AuthorizesMisPermissions, ManagesAdminResource
│   ├── Models/                      # User, Role wrappers, Attachment, BackupRecord, AuthenticationLog
│   ├── Services/                    # Backup, Notification, PermissionRegistrar, TranslationFile
│   ├── Support/                     # Seo, BrowserSessions, AdminNavigation, FlashToast
│   └── Notifications/
├── config/
│   ├── locale.php                   # en/fa/ps
│   ├── mis_permissions.php          # module × verb matrix
│   ├── admin_resources.php          # scaffoldable resources
│   ├── backup.php
│   ├── security.php                 # require admin 2FA, etc.
│   └── kit.php                      # feature flags: seo, api, sms, tenancy, tours
├── database/
│   ├── migrations/                  # permission, activity, notifications, backups, attachments, auth_logs
│   └── seeders/                     # RolesAndPermissions, DemoUsers, KitDemo
├── lang/
│   └── en.json / fa.json / ps.json  # shared UI strings only
├── resources/js/
│   ├── components/
│   │   ├── ui/                      # shadcn (existing)
│   │   ├── mis/                     # MisPage, RowActions, pagination
│   │   ├── admin/                   # AdminResource*, DataTable, ImageUpload
│   │   └── public/                  # SeoHead (optional)
│   ├── layouts/                     # App, Auth, Settings (existing + enhance)
│   ├── pages/
│   │   ├── auth/                    # existing
│   │   ├── settings/                # profile, security, appearance, users, roles, …
│   │   ├── dashboard/               # generic dashboard
│   │   └── resources/               # scaffolded CRUD pages
│   ├── composables/                 # usePermissions, useLocale, useTranslations, useMisPage
│   └── lib/                         # flashToast, theme-colors, seo, format
└── routes/
    ├── web.php
    ├── settings.php
    ├── dashboard.php                # or mis.php
    ├── notifications.php
    └── api.php                      # optional stub
```

### 2.3 Modules: core vs optional

#### Core kit (always on after Phase 1)

1. **Auth** — Fortify login/register/reset/verify, 2FA, passkeys (Chisel-toggleable), session revoke, active-user gate  
2. **RBAC** — Spatie + `mis_permissions` sync + roles UI + `<Can>`  
3. **Users** — admin user CRUD, disable/enable, assign roles  
4. **i18n / RTL** — en/fa/ps, cookie locale, direction sync, shared translations  
5. **Admin shell** — sidebar layout, nav from permissions, dashboard home  
6. **Settings** — profile, security, appearance, language  
7. **Security headers** + admin middleware stack  
8. **Notifications** — DB in-app bell + flash toasts  
9. **Backups** — DB + files, schedule, basic UI  
10. **CRUD conventions** — scaffold command + soft deletes default + flash toast  
11. **CI** — tests + lint workflows  

#### Optional plugins (`config/kit.php` feature flags)

| Flag | Feature | Primary source |
|------|---------|----------------|
| `seo` | SeoHead, sitemap, robots | madrasa |
| `api` | Sanctum admin/public API | madrasa |
| `audit` | activitylog + auth logs + status logs | global-security |
| `attachments` | polymorphic file attachments | global-security |
| `translations_ui` | in-app JSON translation editor | global-security |
| `exports` | CSV/Excel export helpers | Najm patterns (thin) |
| `sms` | SMS gateway drivers | Najm |
| `tenancy` | school/org tenant isolation | Najm |
| `tours` | product tours | greenfield |
| `arabic` | `ar` locale catalog | madrasa |
| `offline` | PWA shell, Dexie outbox, Sanctum sync API | greenfield — see `docs/OFFLINE.md` |

### 2.4 How cloning / customization should work

1. **Clone** repo (or `composer create-project` once published).  
2. Copy `.env.example` → `.env`; set `APP_NAME`, `APP_LOCALE`, `APP_URL`, DB.  
3. Run `php artisan kit:install` (to build): migrate, seed roles, sync permissions, publish lang, optional feature prompts (Chisel-style or custom).  
4. Set brand tokens in `resources/css/app.css` + optional theme-color settings.  
5. Add domain modules under `config/mis_permissions.php` + `config/admin_resources.php` (or hand-rolled controllers using MisPage patterns).  
6. Run `php artisan kit:scaffold {resource}` for boilerplate CRUD.  
7. Never fork kit for domain — **compose**: keep kit upgrades mergable; domain lives in app modules.

**Customization knobs:** `config/kit.php`, `config/locale.php`, `config/mis_permissions.php`, CSS theme variables, `APP_NAME` / logos in settings.

---

## 3. Design pattern extraction

### 3.1 Visual / UX patterns to preserve

1. **Sidebar-first admin shell** — **global-security** floating dark sidebar island, collapse control, glass sticky header with page title (not breadcrumb trail), avatar in header.  
2. **Settings as nested layout** — left subnav for profile / security / appearance / users / roles (GS `layouts/settings/Layout.vue`).  
3. **MIS list pages** — Card header + primary CTA (`SettingsAddButton` / `MisCreateButton`) + filter row + list/table + `MisPagination` + `RowActionsMenu` with permission gates (GS).  
4. **Forms** — sectioned forms in Cards, shadcn inputs, sonner toasts on success/error — not card-heavy dashboards.  
5. **RTL-first** — `dir` on document from locale; logical CSS (`start`/`end`); Plus Jakarta Sans + Noto Sans Arabic.  
6. **Appearance** — light / dark / system via cookie (all modern apps).  
7. **Brand tokens** — CSS variables for primary/accent/surface; kit ships a **neutral teal** professional palette (rebrandable; not GS red, not madrasa gold).  
8. **Empty states & confirm deletes** — `ui-empty-state` + `RowActionsMenu` confirm dialogs.  
9. **Permission-aware UI** — hide/disable actions via `<Can>` / `usePermissions` (GS), never only server-side.  
10. **Motion** — subtle sidebar/header transitions; optional `driver.js` starter tour.

### 3.2 Concrete files to port (UI)

| Pattern | Copy from |
|---------|-----------|
| Shell | `global-security/resources/js/layouts/app/AppSidebarLayout.vue`, `components/AppSidebar.vue`, `NavMain.vue` |
| MIS chrome | `global-security/resources/js/components/MisPage.vue`, `RowActionsMenu.vue`, `composables/useMisPage.ts` |
| Resource CRUD UI | `madrasa-website/resources/js/components/AdminResourceIndex.vue`, `AdminDataTable.vue`, `AdminResourceFormLayout.vue`, … |
| Locale | `global-security/resources/js/composables/useLocale.ts`, `LocaleSync.vue`, `LanguageSwitcher.vue` |
| Permissions UI | `global-security/resources/js/components/Can.vue`, `composables/usePermissions.ts` |
| Theme colors | `global-security/resources/js/lib/theme-colors.ts`, `ThemeColorSettings.vue` |
| SEO | `madrasa-website/resources/js/components/public/SeoHead.vue` |
| Notifications | `NotificationBell.vue` from GS or madrasa |
| Tokens/CSS | Start from `kit/resources/css/app.css`; import font/RTL lessons from madrasa `app.css` |

### 3.3 Backend patterns to port

| Pattern | File(s) |
|---------|---------|
| Permission sync | `global-security/config/mis_permissions.php` + `MisPermissionRegistrar.php` |
| Admin resource registry | `madrasa-website/config/admin_resources.php` + `ManagesAdminResource.php` + `ScaffoldAdminResources.php` |
| Backup | `madrasa-website/app/Services/BackupService.php` + `config/backup.php` |
| Auth logs | `global-security` AuthenticationLog stack |
| Security headers | `madrasa-website/app/Http/Middleware/SecurityHeaders.php` |
| Attachments | `global-security` `HasAttachments` + `Attachment` model |

---

## 4. Feature backlog (phased)

### Phase 1 — MVP clone-ready (must-haves)

> **Done (2026-07-24).** See root `README.md` for clone/setup and login.

Goal: clone kit → migrate → login → manage users/roles → switch fa/ps/en with RTL → use sidebar admin → run a backup → deploy with CI.

- [x] Keep kit Laravel 13 Vue starter as base; init git if desired  
- [x] Add Spatie Permission + `config/mis_permissions.php` + sync command + seeders (`Owner`, `Admin`, `User`)  
- [x] Users management UI (create/edit, roles, disable/enable)  
- [x] Roles management UI (permission matrix)  
- [x] `<Can>` + `usePermissions` + share roles/permissions via Inertia  
- [x] Locale stack: `config/locale.php` (en/fa/ps), SetLocale, switcher, RTL sync, seed JSON catalogs (UI strings only)  
- [x] SecurityHeaders middleware  
- [x] EnsureUserIsActive (+ optional `REQUIRE_ADMIN_TWO_FACTOR` flag in `config/security.php`, off by default)  
- [x] Enhance admin shell nav to be permission-driven  
- [x] Generic dashboard page (stock kit dashboard retained)  
- [x] BackupService + schedule + basic Index UI  
- [x] In-app notifications table + bell  
- [x] Flash toast conventions (`success` / `error`)  
- [x] SoftDeletes convention via `kit:scaffold` stubs  
- [x] `php artisan kit:scaffold` (lean SoftDeletes CRUD stubs)  
- [x] CI: Feature tests for locale, permissions, auth logs *(this pass)*  
- [x] README + `docs/CLONING.md`  

**Explicitly defer from Phase 1:** SEO, API, SMS, tenancy, tours, translation CMS, activitylog, Excel exports, Arabic catalog.

### Phase 2 — Strong MIS defaults

- [x] Spatie activitylog on User (+ package/migrations; no dedicated activity browser UI — auth logs + system logs cover operators)
- [x] Authentication logs (login/logout/2FA/lockout) + settings UI  
- [x] Status change log trait (`HasStatusChangeLogs` + `StatusChangeHistory.vue`; wired on User enable/disable)
- [x] Polymorphic attachments module *(lean trait + download route)*  
- [x] In-app translation file editor (`Settings → Translations`, `KIT_TRANSLATIONS_UI`)
- [x] Org/site settings (key-value) *(logo upload deferred)*  
- [x] Theme color settings (org-persisted + FOUC; Appearance)
- [x] CSV export trait + print layout stub (`ExportsCsv`, `resources/views/print/layout.blade.php`, users export example)
- [x] Sanctum API stub (auth login/me/logout + paginated `/api/users`; `KIT_API`)
- [x] **Offline Foundation 2a** — PWA shell, Dexie outbox, `useOfflineForm`, sync batch API, `field_reports` demo (`KIT_OFFLINE`)  
- [x] **Offline Foundation 2b** — reference-data warm + opted-in list pull/cache  
- [x] Session version revoke-all (Najm pattern)  
- [x] ~~Lookup-table UI~~ — removed from kit core (add in clone if needed; `lookup_items` migration retained)
- [x] Mail notifications channel for critical alerts (`CriticalAlertNotification`; backup failure → Owners)
- [x] Primary locales en/fa/ps (ar/ur catalogs removed from product)  

### Phase 3 — Polish & regional extras

- [x] Product tours (`driver.js` + admin-shell tour + dismiss persistence) *(starter tour only)*  
- [x] Backup UX polish (retention tiers, optional mirror disk, `docs/BACKUPS.md` restore notes)
- [x] ~~SMS plugin foundation~~ — removed from kit core (implement in clone)
- [x] ~~Tenancy foundation~~ — removed from kit core (implement in clone)
- [x] SEO helpers (`Seo` + `SeoHead`) + sitemap/robots stubs  
- [x] Localized digits / Jalali date helpers (`LocalizedDigits`, `useJalali` + `jalaali-js`)
- [x] Slow-request logging + sync metrics channel (`sync` log + `LogSlowRequests`)  
- [x] Offline attachments stubs + conflict UI + metrics beacon (`docs/OFFLINE.md`)  
- [x] Demo data seeder + Storybook-less component gallery (`KitDemoSeeder`, `/dev/gallery` Owner-only)
- [x] `kit:install` interactive wizard  

**Explicitly out of scope / plugin (not kit core):**
- Full multi-tenant SaaS (domains, school platforms, purging) — implement in clone
- Real SMS carrier drivers (Android/Gammu/etc.) — interface + fake only
- Filament panels / Excel-Maatwebsite / CRDT offline sync
- Dedicated Spatie activitylog browser UI (use login/system logs; activity rows still written)

**Offline explicit exclusions:** Automerge/CRDTs, ElectricSQL, PowerSync, SQLite WASM, offline login without prior session.

---

## 5. Explicit exclusions (do NOT copy)

### From madrasa-website
- All domain: admissions, iftah/fatwa, courses, books, mufti workflows, finance/attendance student widgets  
- Public marketing pages content, WhatsApp course helpers  
- Domain-specific permission names (`mufti`, `translator`, iftah actions)  
- Brand-locked gold/emerald/parchment as the **only** kit theme (keep as optional theme preset at most)  
- “Activity feed” widgets that are not real audit logs  

### From global-security
- Domain modules: bidding, projects, procurement, HR/payroll, invoices, org types specific to security contracting  
- Chart pages tied to bidding/finance domain  
- Module keys in `mis_permissions` for those domains (replace with kit generics)  

### From Najm-Almadris
- Filament panels and Filament resources  
- School/teacher/student/exam/tafseer/admission domain  
- Custom school RBAC as the **default** (keep Spatie)  
- Multi-tenant school platform unless building SaaS (offer as optional plugin only)  
- Orphan `config/permission.php` without Spatie package  
- Entire 9k-line domain translation catalogs  

### General
- Hard-coded organization names, logos, WhatsApp numbers  
- Production `.env` secrets  
- Project-specific deploy hosts  

---

## 6. Next implementation steps (ordered)

After user approval of this plan:

1. **Confirm decisions** (see open questions below).  
2. **Init repo hygiene** in `kit`: README pointing to this plan; `.gitignore` already present; optional `git init`.  
3. **Add core packages:** `spatie/laravel-permission`, later `spatie/laravel-activitylog` (Phase 2).  
4. **Port locale + RTL** from global-security/madrasa into kit (smallest visible win).  
5. **Port permission registrar + seeders + roles/users UI** from global-security.  
6. **Wire middleware stack** in `bootstrap/app.php`: SetLocale, SecurityHeaders, EnsureUserIsActive, permission aliases.  
7. **Permission-aware navigation** + empty dashboard.  
8. **Port BackupService** from madrasa + schedule + UI.  
9. **Notifications bell** + migrations.  
10. **Scaffold command** (slim port of madrasa `admin:scaffold`) + one example resource (`notes` or `departments`).  
11. **Feature tests** for auth, locale, permissions, backups.  
12. **Write `docs/CLONING.md`** and Phase 1 checklist completion.  
13. Only then start Phase 2 items.

**Suggested first PR slice:** locale + RTL + permissions sync + users/roles UI (no backups yet).

---

## 7. Open questions / blockers for the user

1. **Default locale** for new clones: `fa`, `ps`, or `en`?  
2. **Arabic (`ar`) in Phase 1** or keep as optional Phase 2 flag? (madrasa has it; GS does not.)  
3. **Urdu (`ur`)** — include, or Afghanistan-primary only (en/fa/ps)?  
4. **Passkeys** — keep enabled by default (kit/madrasa) or Chisel-opt-in only?  
5. **Backup approach** — confirm custom BackupService (madrasa/GS) vs Spatie Backup package (Najm)? Recommendation: custom.  
6. **Multi-tenancy** — needed in core for your next projects, or strictly optional plugin?  
7. **SMS** — required in Phase 2 for most Afghan MIS deploys, or rare enough to stay optional?  
8. **Public SEO** — will most clones be private MIS (skip SEO) or mixed public+admin (include SeoHead early)?  
9. **Package name / branding** — `yaqeen/mis-kit`? App name placeholder?  
10. **Should `kit` become a git repo now** and track this plan, or stay local until Phase 1 lands?

---

## Appendix A — Source path cheat sheet

### madrasa-website
- Auth: `app/Providers/FortifyServiceProvider.php`, `app/Http/Middleware/EnsureAdminTwoFactor.php`
- RBAC: `app/Support/AdminPermissions.php`, `database/seeders/PermissionSeeder.php`
- CRUD: `config/admin_resources.php`, `app/Http/Controllers/Admin/Concerns/ManagesAdminResource.php`
- i18n: `config/locales.php`, `lang/{en,fa,ps,ar,ur}.json`, `resources/js/composables/useLocaleDirection.ts`
- Backup: `app/Services/BackupService.php`, `config/backup.php`
- SEO: `app/Support/Seo.php`, `resources/js/components/public/SeoHead.vue`
- Security: `app/Http/Middleware/SecurityHeaders.php`
- CI: `.github/workflows/tests.yml`, `lint.yml`

### global-security
- Permissions: `config/mis_permissions.php`, `app/Services/MisPermissionRegistrar.php`
- i18n: `config/locale.php`, `resources/js/composables/useLocale.ts`
- Audit: activitylog models + `AuthenticationLog*` + `HasStatusChangeLogs`
- UI: `MisPage.vue`, `RowActionsMenu.vue`, `Can.vue`
- Backups: `app/Services/BackupService.php`, `pages/settings/Backups/`
- Attachments: `app/Models/Attachment.php`, `HasAttachments.php`

### Najm-Almadris
- Tenancy: `BelongsToSchool.php`, `ResolveSchoolTenant.php`, `TenantStorage.php`
- Sessions: `UserSessionManager.php`, `EnsureValidSessionVersion.php`
- Backups (package): `spatie/laravel-backup` + school/platform UI

### kit (current base)
- Stock Fortify auth, settings, shadcn-vue, Tailwind 4, CI tests workflow  
- No README/docs until this plan  
- No Spatie Permission yet — add in Phase 1  

---

## Appendix B — Success criteria for Phase 1

A developer can:

1. Clone `kit`, configure `.env`, `composer install && npm install && php artisan migrate --seed`.  
2. Log in as seeded owner; enable 2FA.  
3. Switch language among English / Dari / Pashto and see RTL flip correctly.  
4. Create a user, assign a role with subset of permissions; verify nav and actions hide.  
5. Create a scaffolded resource and get working Index/Create/Edit pages.  
6. Trigger a backup from UI and download it.  
7. Push to GitHub and see CI pass.

---

*End of planning document.*
