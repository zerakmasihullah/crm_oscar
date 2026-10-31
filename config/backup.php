<?php

return [

    'disk' => env('BACKUP_DISK', 'backups'),

    /*
    | Optional secondary local/mirror disk (e.g. another volume). Set null to skip.
    */
    'mirror_disk' => env('BACKUP_MIRROR_DISK'),

    /*
    | Paths included in "full" backups (storage uploads / private files).
    | Backup archives themselves are never included.
    */
    'include_paths' => [
        storage_path('app/public'),
        storage_path('app/private'),
    ],

    'exclude_patterns' => [
        '*/node_modules/*',
        '*/vendor/*',
        '*/.git/*',
        '*/storage/app/backups/*',
        '*/storage/app/backup-temp/*',
        '*/storage/framework/*',
        '*/storage/logs/*',
        '*/storage/pail/*',
    ],

    /*
    | Retention tiers — keep N completed backups per tier.
    | Legacy BACKUP_RETENTION still works as a flat fallback for "manual".
    | pre_restore safety copies are kept separately and never auto-pruned below keep_pre_restore.
    */
    'retention' => [
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 7),
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 6),
        'manual' => (int) env('BACKUP_RETENTION_MANUAL', env('BACKUP_RETENTION', 10)),
        'pre_restore' => (int) env('BACKUP_RETENTION_PRE_RESTORE', 5),
    ],

    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    'mysql_path' => env('MYSQL_PATH', 'mysql'),

    /*
    | Default scheduled auto-backup type when OrgSetting is unset.
    */
    'auto_type' => env('BACKUP_AUTO_TYPE', 'full'),

    'auto_time' => env('BACKUP_AUTO_TIME', '02:00'),

    /*
    | Queue name for RunBackupJob (manual + scheduled dispatches).
    */
    'queue' => env('BACKUP_QUEUE', 'default'),

];
