<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class PruneMisLogsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $days = 90,
        public bool $dryRun = false,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        $options = [
            '--days' => max(7, $this->days),
        ];

        if ($this->dryRun) {
            $options['--dry-run'] = true;
        }

        Artisan::call('mis:prune-logs', $options);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
