<?php

namespace App\Console\Commands;

use App\Jobs\RunBackupJob;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

class RunBackupCommand extends Command
{
    protected $signature = 'backup:run
        {--type= : database, files, or full (default: full, or org auto setting when scheduled)}
        {--scheduled : Mark as scheduled trigger and respect auto-backup toggle}
        {--sync : Run inline instead of dispatching RunBackupJob}';

    protected $description = 'Create a database, files, or full system backup archive';

    public function handle(BackupService $backups): int
    {
        $scheduled = (bool) $this->option('scheduled');

        if ($scheduled && ! $backups->autoEnabled()) {
            $this->info('Auto backup is disabled. Skipping.');

            return self::SUCCESS;
        }

        $type = $this->option('type')
            ?: ($scheduled ? $backups->autoType() : 'full');

        if (! in_array($type, BackupService::TYPES, true)) {
            $this->error('Invalid type. Use database, files, or full.');

            return self::FAILURE;
        }

        $trigger = $scheduled ? 'scheduled' : 'manual';

        try {
            if ($this->option('sync') || config('queue.default') === 'sync') {
                $record = $backups->run($type, $trigger);
                $this->info("Backup completed: {$record->name}");

                return self::SUCCESS;
            }

            Bus::dispatch(new RunBackupJob($type, $trigger));
            $this->info("Backup queued ({$type} / {$trigger}).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
