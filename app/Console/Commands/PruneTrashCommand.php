<?php

namespace App\Console\Commands;

use App\Services\TrashRegistry;
use Illuminate\Console\Command;

class PruneTrashCommand extends Command
{
    protected $signature = 'trash:prune
                            {--days= : Override retention days (default from config)}
                            {--dry-run : Show count without permanently deleting}';

    protected $description = 'Permanently delete soft-deleted trash items older than retention';

    public function handle(TrashRegistry $trash): int
    {
        $days = $this->option('days');
        $days = $days !== null && $days !== ''
            ? max(1, (int) $days)
            : (int) config('trash.retention_days', 60);

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info("Trash retention cutoff: {$cutoff->toDateTimeString()} ({$days} days)");

        if ($dryRun) {
            $count = 0;

            foreach ($trash->types() as $entry) {
                $modelClass = $entry['model'];

                if (! in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                    continue;
                }

                $count += $modelClass::onlyTrashed()->where('deleted_at', '<', $cutoff)->count();
            }

            $this->comment("Dry run: {$count} item(s) would be permanently deleted.");

            return self::SUCCESS;
        }

        $purged = $trash->prune($days);
        $this->info("Permanently deleted {$purged} item(s) from trash.");

        return self::SUCCESS;
    }
}
