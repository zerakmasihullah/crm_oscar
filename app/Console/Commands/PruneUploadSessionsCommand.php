<?php

namespace App\Console\Commands;

use App\Services\ChunkedUploadService;
use Illuminate\Console\Command;

class PruneUploadSessionsCommand extends Command
{
    protected $signature = 'uploads:prune
                            {--dry-run : Show what would be cleaned without deleting}';

    protected $description = 'Expire stale chunked upload sessions and delete leftover chunk data';

    public function handle(ChunkedUploadService $uploads): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $uploads->prune($dryRun);

        $this->info(($dryRun ? '[dry-run] ' : '')."Expired active sessions: {$result['expired']}");
        $this->info(($dryRun ? '[dry-run] ' : '')."Removed stale failed/aborted/expired rows: {$result['failed_stale']}");

        return self::SUCCESS;
    }
}
