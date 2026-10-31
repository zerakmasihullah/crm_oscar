# Backups

Custom `BackupService` — no Spatie package. Optimized mysqldump / file zip with checksums, retention tiers, optional mirror disk, one-click restore, and activity logging.

## Where

**Settings → Backups** only (not in the main sidebar).

## Manual backup

| Action | Contents |
|--------|----------|
| **Backup database** | SQL dump only |
| **Backup storage files** | `storage/app/public` + `storage/app/private` |
| **Backup full system** | Database + storage files |

CLI:

```bash
php artisan backup:run --type=full
php artisan backup:run --type=database
php artisan backup:run --type=files
```

## Auto backup

Toggle **Enable daily auto backup** on the settings page and choose type (`full` or `database`).

- Stored in `org_settings` (`backup_auto_enabled`, `backup_auto_type`)
- Scheduler: `backup:run --scheduled` daily at `BACKUP_AUTO_TIME` (default `02:00`)
- Skips when auto backup is disabled

Requires `php artisan schedule:work` (or cron) in production.

## Restore

From the backup row → **Restore**:

1. A **safety copy** (`pre_restore` full backup) of the current system is created first.
2. You choose **Full system**, **Database only**, or **Storage files only** (based on what that archive contains).
3. Newer backups stay listed and on disk:
   - MySQL dumps exclude `backup_records` and `activity_log`
   - SQLite restores re-import the backup catalog after the file swap
4. Every create / restore / delete / auto-settings change is written to the `backups` activity log and `storage/logs`.

## Retention tiers

| Tier | When | Default keep |
|------|------|--------------|
| `manual` | UI / CLI | 10 |
| `daily` | Scheduled, normal day | 7 |
| `weekly` | Scheduled on Sunday | 4 |
| `monthly` | Scheduled on the 1st | 6 |
| `pre_restore` | Safety copies before restore | 5 |

Override with `BACKUP_RETENTION_*` env vars.

## Mirror disk

Set `BACKUP_MIRROR_DISK` to a second filesystem disk to copy each completed archive.

## Permissions

`backups.view`, `backups.run`, `backups.download`, `backups.restore`, `backups.delete`

After changing `config/mis_permissions.php`:

```bash
php artisan mis:sync-permissions
```
