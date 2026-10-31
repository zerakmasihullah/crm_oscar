<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class PruneTrashJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public ?int $days = null,
        public bool $dryRun = false,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        $options = [];

        if ($this->days !== null) {
            $options['--days'] = max(1, $this->days);
        }

        if ($this->dryRun) {
            $options['--dry-run'] = true;
        }

        Artisan::call('trash:prune', $options);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
