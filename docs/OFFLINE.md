# Offline Foundation

Feature-flagged offline platform for intermittent connectivity (field forms in Afghan MIS deployments).

Enable with:

```env
KIT_OFFLINE=true
```

Or from the admin UI: **Settings → Offline & PWA** (requires `settings.manage`). Saved values live in `org_settings` and override the env default at runtime.

Then: `composer require laravel/sanctum` (if not installed), `php artisan migrate`, `npm install`, `npm run build`.

## Admin settings UI

`/settings/offline` controls:

- Offline mode on/off
- PWA / service worker registration
- Auto-sync, warm cache
- Offline attachments + max bytes
- Sync metrics + slow-request threshold
- Batch / outbox limits
- Sync device revoke (deletes the bound Sanctum token)

Runtime reads use `App\Support\OfflineSettings` (OrgSetting overrides + env defaults). Client settings (`max_batch_size`, `max_outbox_size`, MIME allow-list, registered `resources` / `warm_resources`, cache TTL) are shared on every Inertia page and honored by the Dexie outbox.

---

## Architecture

```
Vue UI → OfflineResourceForm / useOfflineResourceList / useOfflineForm
       → Dexie (per-user IndexedDB): drafts, cache, sync_outbox, attachments, conflicts
       → Sync coordinator (online / visibility / Background Sync)
       → POST /api/sync/attachments (multipart) then POST /api/sync/batch (JSON)
       → AbstractOfflineSyncHandler → Eloquent (idempotent user_id + client_uuid)
```

**Automatic for every AppLayout page when offline is enabled:**

- Offline banner (pending count + Sync now)
- Conflict resolution panel (Keep my version / Use server version)
- Per-user IndexedDB bind on login / wipe on logout
- Pull/warm of every resource registered in `config/offline.php`

**Automatic for every new offline resource via scaffold:**

```bash
php artisan kit:scaffold FieldNote --offline
# optional:
php artisan kit:scaffold FieldNote --offline --attachments
```

That generates and registers:

1. Model (`client_uuid`, SoftDeletes, optional `HasAttachments` / user scope)
2. Migration columns patched automatically
3. Sync handler extending `AbstractOfflineSyncHandler`
4. `config/offline.php` `resources` + `warm.resources` (+ `attachment_parents` when `--attachments`)
5. `config/mis_permissions.php` module entry
6. Create/Edit using `OfflineResourceForm` + `useOfflineResourceForm`
7. Index using `useOfflineResourceList` (cached/queued rows offline)
8. Controller props via `SharesOfflineInertiaProps`

You still add routes and run migrate / `mis:sync-permissions`.

## Shared primitives (use these — do not reinvent)

| Primitive | Path | Use for |
|-----------|------|---------|
| `OfflineResourceForm` | `resources/js/components/OfflineResourceForm.vue` | Create/Edit shells (status line, attachments, submit) |
| `useOfflineResourceForm` | `resources/js/offline/useOfflineResourceForm.ts` | Form state + queue toast + attachment IDs |
| `useOfflineResourceList` | `resources/js/offline/useOfflineResourceList.ts` | Index pages: online list or offline cache |
| `useOfflineForm` | `resources/js/offline/useOfflineForm.ts` | Low-level queue/submit (prefer the resource helpers) |
| `listOfflineResource` | `resources/js/offline/repository.ts` | Read any resource cache by key |
| `SharesOfflineInertiaProps` | `app/Http/Controllers/Concerns/...` | Controllers: `offlineEnabled` + `offlineResource` |
| `AbstractOfflineSyncHandler` | `app/Sync/AbstractOfflineSyncHandler.php` | LWW upsert/delete/pull handlers |
| `HasOfflineSync` | `app/Concerns/HasOfflineSync.php` | Model fillable marker for sync fields |

Settings / RBAC admin screens stay online-only by design.

## Per-user IndexedDB lifecycle

| Event | Behavior |
|-------|----------|
| Login / navigation as user A | Open `yaqeen_kit_offline_u{A}` (or `…_o{org}_u{A}` when org is present) |
| Switch to user B | Delete user A DB, bind user B |
| Logout | Delete current user DB (outbox, cache, blobs, sync token) |
| Legacy DB `yaqeen_kit_offline` | Deleted on first bind (shared-device safety) |

## Libraries

| Layer | Package |
|-------|---------|
| PWA / SW | `vite-plugin-pwa` + Workbox (`injectManifest`) |
| Local DB | `dexie` |
| Connectivity | `@vueuse/core` `useOnline` |
| Auth for sync | Laravel Sanctum (session for main thread; device-bound PAT in IDB) |

## Browser support

| Feature | Chrome/Edge | Safari | Firefox |
|---------|-------------|--------|---------|
| App shell / precache | Yes | Yes | Yes |
| IndexedDB outbox | Yes | Yes | Yes |
| Background Sync API | Yes | No | No |

Safari/Firefox fall back to flush-on-`online` and flush-on-visibility. The service worker never caches `/api/sync/*` responses (NetworkOnly).

## Manual opt-in (without scaffold)

1. Add `client_uuid` (uuid, unique) + `client_updated_at` + SoftDeletes.
2. Extend `AbstractOfflineSyncHandler` (or implement `SyncsOfflineMutations`).
3. Register in `config/offline.php` → `resources` and usually `warm.resources`.
4. For attachments: use `HasAttachments` and add `attachment_parents.{resource}`.
5. Controller: `SharesOfflineInertiaProps` / `OfflineSettings::enabled()`.
6. Vue: `OfflineResourceForm` + `useOfflineResourceList` (conflict panel is already in AppLayout).

## Demo resource

`field_reports` is the reference backend for shared offline primitives (sync handler, attachments, trash). There is no Field Reports admin UI in the kit — use `field_notes` or a scaffolded module for list/form demos.

## Security

- Do not cache API tokens in the service worker precache; store the sync PAT only in IndexedDB `sync_meta`.
- Each sync device owns its own Sanctum token (`offline-sync:{device_id}`); revoking a device deletes that token.
- Batch/attachment requests require a non-revoked `device_id` matching the authenticated user.
- Idempotency ledger is scoped by `(user_id, client_uuid)`.
- Attachment downloads require ownership (`uploaded_by` or attachable `user_id`).
- CSP includes `worker-src 'self'` when offline is enabled.
- No offline login without a prior online session.

## Storage & quotas

- Outbox and attachment writes use quota-aware error messages.
- Synced outbox rows and uploaded blobs are pruned after a successful flush.
- Settings page shows approximate browser storage usage when the Storage API is available.

## Observability

- Log channel: `sync` → `storage/logs/sync.log`
- Slow request middleware logs requests over `offline.slow_request_ms`
- Client may POST flush timings to `/api/sync/metrics` when metrics are enabled

## Operator troubleshooting

| Symptom | Check |
|---------|-------|
| Pending never clears | Auto-sync off? Device revoked? Open Network for `/api/sync/batch` |
| New module never syncs | Is it in `config/offline.php` `resources`? Did scaffold `--offline` run? |
| Attachments missing | Toggle enabled? Resource in `attachment_parents`? MIME/size rejected? |
| Wrong user's drafts on shared PC | Confirm logout wiped IDB; DB name should include user id |
| PWA not installing | `pwa_enabled`, HTTPS (or localhost), `npm run build` |
| 503 on sync | Offline mode disabled in Settings → Offline & PWA |

## Tests

```bash
npm run test:unit
php artisan test --filter=Offline
```

## Explicit non-goals

- Offline auth without prior session
- Offline RBAC / settings administration
- Caching every Inertia HTML page
- Multi-user CRDT / Automerge merge
