<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public string $type = 'full',
        public string $trigger = 'manual',
        public ?int $userId = null,
    ) {
        $this->onQueue((string) config('backup.queue', 'default'));
    }

    public function handle(BackupService $backups): void
    {
        $backups->run($this->type, $this->trigger, $this->userId);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
