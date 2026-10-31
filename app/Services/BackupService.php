<?php

namespace App\Services;

use App\Models\BackupRecord;
use App\Models\OrgSetting;
use App\Models\User;
use App\Notifications\CriticalAlertNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BackupService
{
    public const TYPES = ['database', 'files', 'full'];

    public const RESTORE_TARGETS = ['database', 'files', 'full'];

    public function run(string $type = 'full', string $trigger = 'manual', ?int $userId = null): BackupRecord
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new RuntimeException("Invalid backup type: {$type}");
        }

        $uuid = (string) Str::uuid();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $name = "{$type}_{$timestamp}";
        $disk = config('backup.disk', 'backups');
        $tier = $this->resolveRetentionTier($trigger);

        $record = BackupRecord::query()->create([
            'uuid' => $uuid,
            'name' => $name,
            'type' => $type,
            'trigger' => $trigger,
            'retention_tier' => $tier,
            'disk' => $disk,
            'path' => "archives/{$uuid}.zip",
            'status' => 'pending',
            'created_by' => $userId,
        ]);

        $tempDir = storage_path("app/backup-temp/{$uuid}");

        try {
            File::ensureDirectoryExists($tempDir);

            $filesToArchive = [];
            $layers = [];

            if (in_array($type, ['database', 'full'], true)) {
                $dumpPath = $this->dumpDatabase($tempDir);
                $filesToArchive[] = $dumpPath;
                $layers['database'] = [
                    'file' => basename($dumpPath),
                    'checksum' => hash_file('sha256', $dumpPath),
                    'size' => filesize($dumpPath) ?: 0,
                ];
            }

            if (in_array($type, ['files', 'full'], true)) {
                $filesArchive = $this->archiveStorageFiles($tempDir);
                $filesToArchive[] = $filesArchive;
                $layers['files'] = [
                    'file' => basename($filesArchive),
                    'checksum' => hash_file('sha256', $filesArchive),
                    'size' => filesize($filesArchive) ?: 0,
                ];
            }

            $manifest = [
                'version' => 1,
                'app' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'type' => $type,
                'layers' => $layers,
                'database_connection' => config('database.default'),
            ];

            $manifestPath = "{$tempDir}/manifest.json";
            File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $filesToArchive[] = $manifestPath;

            $archivePath = "{$tempDir}/{$name}.zip";
            $this->zipFiles($archivePath, $filesToArchive);

            $checksum = hash_file('sha256', $archivePath);
            $size = filesize($archivePath) ?: 0;

            Storage::disk($disk)->put($record->path, file_get_contents($archivePath) ?: '');

            $mirrorPath = $this->mirrorToSecondaryLayer($disk, $record->path, $archivePath);

            $record->update([
                'status' => 'completed',
                'size' => $size,
                'checksum' => $checksum,
                'mirror_path' => $mirrorPath,
            ]);

            // Never prune while creating a pre-restore safety copy — the
            // backup being restored must stay on disk for the restore step.
            if ($trigger !== 'pre_restore') {
                $this->applyRetention();
            }

            $this->logEvent('backup.created', $record, [
                'type' => $type,
                'trigger' => $trigger,
                'size' => $size,
            ], $userId);
        } catch (Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            $this->logEvent('backup.failed', $record, [
                'type' => $type,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
            ], $userId);

            $this->notifyBackupFailure($e->getMessage());

            throw $e;
        } finally {
            File::deleteDirectory($tempDir);
        }

        return $record->fresh();
    }

    /**
     * Restore from a backup without discarding newer backup archives.
     *
     * Always takes a safety "pre_restore" backup first, then restores the
     * requested layers. After a database restore, the backup_records catalog
     * is re-applied from a sidecar so later backups stay visible.
     *
     * @param  'database'|'files'|'full'  $target
     */
    public function restore(BackupRecord $record, string $target = 'full', ?int $userId = null): BackupRecord
    {
        if ($record->status !== 'completed') {
            throw new RuntimeException('Only completed backups can be restored.');
        }

        if (! in_array($target, self::RESTORE_TARGETS, true)) {
            throw new RuntimeException("Invalid restore target: {$target}");
        }

        $manifest = $this->readManifest($record);
        $available = array_keys($manifest['layers'] ?? []);

        if (in_array($target, ['database', 'full'], true) && ! in_array('database', $available, true)) {
            throw new RuntimeException('This backup does not include a database dump.');
        }

        if (in_array($target, ['files', 'full'], true) && ! in_array('files', $available, true)) {
            throw new RuntimeException('This backup does not include storage files.');
        }

        $safety = $this->run('full', 'pre_restore', $userId);
        $catalogPath = $this->exportCatalog();

        $tempDir = storage_path('app/backup-temp/restore-'.Str::uuid());

        try {
            File::ensureDirectoryExists($tempDir);
            $this->extractArchive($record, $tempDir);

            if (in_array($target, ['database', 'full'], true)) {
                $this->restoreDatabase("{$tempDir}/database.sql");
                $this->importCatalog($catalogPath);
            }

            if (in_array($target, ['files', 'full'], true)) {
                $this->restoreStorageFiles("{$tempDir}/files.zip");
            }

            $this->logEvent('backup.restored', $record, [
                'target' => $target,
                'safety_backup_uuid' => $safety->uuid,
                'from' => $record->name,
            ], $userId);

            Log::channel('single')->info('Backup restored', [
                'backup_uuid' => $record->uuid,
                'target' => $target,
                'safety_backup_uuid' => $safety->uuid,
                'user_id' => $userId,
            ]);

            return $safety;
        } catch (Throwable $e) {
            $this->logEvent('backup.restore_failed', $record, [
                'target' => $target,
                'error' => $e->getMessage(),
                'safety_backup_uuid' => $safety->uuid,
            ], $userId);

            throw $e;
        } finally {
            File::deleteDirectory($tempDir);

            if (is_file($catalogPath)) {
                @unlink($catalogPath);
            }
        }
    }

    public function autoEnabled(): bool
    {
        return OrgSetting::getValue('backup_auto_enabled', '0') === '1';
    }

    public function autoType(): string
    {
        $type = (string) OrgSetting::getValue('backup_auto_type', config('backup.auto_type', 'full'));

        return in_array($type, self::TYPES, true) ? $type : 'full';
    }

    /**
     * @return array{enabled: bool, type: string}
     */
    public function autoSettings(): array
    {
        return [
            'enabled' => $this->autoEnabled(),
            'type' => $this->autoType(),
        ];
    }

    public function updateAutoSettings(bool $enabled, string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new RuntimeException("Invalid auto backup type: {$type}");
        }

        OrgSetting::setValue('backup_auto_enabled', $enabled ? '1' : '0');
        OrgSetting::setValue('backup_auto_type', $type);

        $this->logEvent('backup.auto_settings_updated', null, [
            'enabled' => $enabled,
            'type' => $type,
        ], auth()->user()?->id);
    }

    public function delete(BackupRecord $record): void
    {
        $this->logEvent('backup.deleted', $record, [
            'name' => $record->name,
            'type' => $record->type,
        ], auth()->user()?->id);

        $record->delete();
    }

    public function downloadPath(BackupRecord $record): string
    {
        $disk = Storage::disk($record->disk);

        if (! $disk->exists($record->path)) {
            throw new RuntimeException('Backup file not found.');
        }

        return $disk->path($record->path);
    }

    /**
     * @return array{version?: int, type?: string, layers?: array<string, mixed>}
     */
    public function readManifest(BackupRecord $record): array
    {
        $tempDir = storage_path('app/backup-temp/manifest-'.Str::uuid());

        try {
            File::ensureDirectoryExists($tempDir);
            $this->extractArchive($record, $tempDir);

            $manifestPath = "{$tempDir}/manifest.json";

            if (! is_file($manifestPath)) {
                // Legacy database-only archives
                return [
                    'version' => 0,
                    'type' => $record->type,
                    'layers' => is_file("{$tempDir}/database.sql")
                        ? ['database' => ['file' => 'database.sql']]
                        : [],
                ];
            }

            /** @var array{version?: int, type?: string, layers?: array<string, mixed>} $manifest */
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            return $manifest;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    protected function dumpDatabase(string $tempDir): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $dumpPath = "{$tempDir}/database.sql";

        if ($driver === 'sqlite') {
            $database = config("database.connections.{$connection}.database");

            if (! is_string($database) || ! is_file($database)) {
                throw new RuntimeException('SQLite database file not found.');
            }

            File::copy($database, $dumpPath);

            return $dumpPath;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $host = config("database.connections.{$connection}.host");
            $port = config("database.connections.{$connection}.port");
            $database = config("database.connections.{$connection}.database");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");
            $mysqldump = config('backup.mysqldump_path', 'mysqldump');

            // Keep backup catalog + activity trail intact across restores so newer
            // backups remain listed even when rolling the app DB back in time.
            $process = new Process([
                $mysqldump,
                "--host={$host}",
                "--port={$port}",
                "--user={$username}",
                '--single-transaction',
                '--quick',
                '--lock-tables=false',
                '--routines',
                '--triggers',
                '--ignore-table='.$database.'.backup_records',
                '--ignore-table='.$database.'.activity_log',
                (string) $database,
            ]);

            $process->setEnv(['MYSQL_PWD' => (string) $password]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());
            }

            File::put($dumpPath, $process->getOutput());

            if (! is_file($dumpPath) || filesize($dumpPath) === 0) {
                throw new RuntimeException('Database backup file is empty.');
            }

            return $dumpPath;
        }

        throw new RuntimeException("Unsupported database driver for backup: {$driver}");
    }

    protected function archiveStorageFiles(string $tempDir): string
    {
        $archivePath = "{$tempDir}/files.zip";
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create storage files archive.');
        }

        $basePath = storage_path('app');
        $count = 0;

        foreach (config('backup.include_paths', []) as $includePath) {
            if (! File::isDirectory($includePath) && ! File::isFile($includePath)) {
                continue;
            }

            $paths = File::isDirectory($includePath)
                ? File::allFiles($includePath)
                : [new \SplFileInfo($includePath)];

            foreach ($paths as $file) {
                $absolutePath = $file->getPathname();

                if ($this->shouldExcludePath($absolutePath)) {
                    continue;
                }

                $relative = ltrim(str_replace($basePath, '', $absolutePath), DIRECTORY_SEPARATOR);
                $zip->addFile($absolutePath, 'storage/'.str_replace('\\', '/', $relative));
                $count++;
            }
        }

        $zip->close();

        if ($count === 0) {
            // Still create a valid empty zip with a marker so restore knows files layer exists
            $zip = new ZipArchive;
            $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('.empty', '');
            $zip->close();
        }

        return $archivePath;
    }

    protected function shouldExcludePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach (config('backup.exclude_patterns', []) as $pattern) {
            $regex = '/^'.str_replace('\*', '.*', preg_quote(str_replace('\\', '/', $pattern), '/')).'$/i';

            if (preg_match($regex, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $files
     */
    protected function zipFiles(string $archivePath, array $files): void
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();
    }

    protected function extractArchive(BackupRecord $record, string $tempDir): void
    {
        $source = $this->downloadPath($record);
        $zip = new ZipArchive;

        if ($zip->open($source) !== true) {
            throw new RuntimeException('Unable to open backup archive.');
        }

        $zip->extractTo($tempDir);
        $zip->close();
    }

    protected function restoreDatabase(string $dumpPath): void
    {
        if (! is_file($dumpPath)) {
            throw new RuntimeException('Database dump missing from backup archive.');
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $database = config("database.connections.{$connection}.database");

            DB::purge($connection);
            File::copy($dumpPath, $database);
            DB::reconnect($connection);

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $host = config("database.connections.{$connection}.host");
            $port = config("database.connections.{$connection}.port");
            $database = config("database.connections.{$connection}.database");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");
            $mysql = config('backup.mysql_path', 'mysql');

            $process = new Process([
                $mysql,
                "--host={$host}",
                "--port={$port}",
                "--user={$username}",
                (string) $database,
            ]);

            $process->setEnv(['MYSQL_PWD' => (string) $password]);
            $process->setInput(File::get($dumpPath));
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Database restore failed: '.$process->getErrorOutput());
            }

            DB::purge($connection);
            DB::reconnect($connection);

            return;
        }

        throw new RuntimeException("Unsupported database driver for restore: {$driver}");
    }

    protected function restoreStorageFiles(string $filesZipPath): void
    {
        if (! is_file($filesZipPath)) {
            throw new RuntimeException('Storage files archive missing from backup.');
        }

        $tempDir = storage_path('app/backup-temp/files-'.Str::uuid());
        File::ensureDirectoryExists($tempDir);

        try {
            $zip = new ZipArchive;

            if ($zip->open($filesZipPath) !== true) {
                throw new RuntimeException('Unable to open storage files archive.');
            }

            $zip->extractTo($tempDir);
            $zip->close();

            $sourceRoot = "{$tempDir}/storage";

            if (! is_dir($sourceRoot)) {
                return;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $relative = substr($file->getPathname(), strlen($sourceRoot) + 1);
                $destination = storage_path('app/'.$relative);

                if ($this->shouldExcludePath($destination)) {
                    continue;
                }

                if ($file->isDir()) {
                    File::ensureDirectoryExists($destination);

                    continue;
                }

                File::ensureDirectoryExists(dirname($destination));
                File::copy($file->getPathname(), $destination);
            }
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    protected function exportCatalog(): string
    {
        $rows = BackupRecord::query()->orderBy('id')->get()->map(fn (BackupRecord $r) => [
            'uuid' => $r->uuid,
            'name' => $r->name,
            'type' => $r->type,
            'trigger' => $r->trigger,
            'retention_tier' => $r->retention_tier ?? 'manual',
            'disk' => $r->disk,
            'path' => $r->path,
            'mirror_path' => $r->mirror_path,
            'status' => $r->status,
            'size' => $r->size,
            'checksum' => $r->checksum,
            'error' => $r->error,
            'created_by' => $r->created_by,
            'created_at' => $r->created_at?->toDateTimeString(),
            'updated_at' => $r->updated_at?->toDateTimeString(),
        ])->all();

        $path = storage_path('app/backups/catalog-'.Str::uuid().'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($rows, JSON_PRETTY_PRINT));

        return $path;
    }

    protected function importCatalog(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $record = BackupRecord::query()->firstOrNew(['uuid' => $row['uuid']]);
            $record->fill([
                'name' => $row['name'],
                'type' => $row['type'],
                'trigger' => $row['trigger'],
                'retention_tier' => $row['retention_tier'] ?? 'manual',
                'disk' => $row['disk'],
                'path' => $row['path'],
                'mirror_path' => $row['mirror_path'] ?? null,
                'status' => $row['status'],
                'size' => $row['size'] ?? null,
                'checksum' => $row['checksum'] ?? null,
                'error' => $row['error'] ?? null,
                'created_by' => $row['created_by'] ?? null,
            ]);
            $record->created_at = $row['created_at'] ?? $record->created_at ?? now();
            $record->updated_at = $row['updated_at'] ?? now();
            $record->save();
        }
    }

    protected function mirrorToSecondaryLayer(string $primaryDisk, string $path, string $localArchive): ?string
    {
        $mirrorDisk = config('backup.mirror_disk');

        if (! $mirrorDisk || $mirrorDisk === $primaryDisk) {
            return null;
        }

        Storage::disk($mirrorDisk)->put($path, file_get_contents($localArchive) ?: '');

        return $path;
    }

    protected function resolveRetentionTier(string $trigger): string
    {
        if ($trigger === 'pre_restore') {
            return 'pre_restore';
        }

        if ($trigger === 'manual') {
            return 'manual';
        }

        if (now()->day === 1) {
            return 'monthly';
        }

        if (now()->isSunday()) {
            return 'weekly';
        }

        return 'daily';
    }

    public function applyRetention(): void
    {
        $tiers = config('backup.retention');

        if (! is_array($tiers)) {
            $keep = max(1, (int) $tiers);
            $this->pruneTier(null, $keep);

            return;
        }

        foreach ($tiers as $tier => $limit) {
            $this->pruneTier((string) $tier, max(1, (int) $limit));
        }
    }

    protected function pruneTier(?string $tier, int $keep): void
    {
        $query = BackupRecord::query()->where('status', 'completed');

        if ($tier !== null) {
            $query->where('retention_tier', $tier);
        }

        $old = $query->orderByDesc('id')->skip($keep)->take(100)->get();

        foreach ($old as $record) {
            $this->delete($record);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function logEvent(string $event, ?BackupRecord $record, array $properties = [], ?int $userId = null): void
    {
        try {
            $logger = activity('backups')
                ->event($event)
                ->withProperties($properties);

            if ($record) {
                $logger->performedOn($record);
            }

            if ($userId) {
                $user = User::query()->find($userId);

                if ($user) {
                    $logger->causedBy($user);
                }
            }

            $logger->log($event);
        } catch (Throwable $e) {
            Log::warning('Backup activity log failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyBackupFailure(string $message): void
    {
        if (! config('kit.features.mail_alerts', true)) {
            return;
        }

        try {
            User::role('Owner')->get()->each(function (User $user) use ($message): void {
                $user->notify(new CriticalAlertNotification(
                    'Backup failed',
                    $message,
                    '/settings/backups',
                ));
            });
        } catch (Throwable) {
            // Role may not exist yet during early setup.
        }
    }
}
