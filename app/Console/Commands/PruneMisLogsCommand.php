<?php

namespace App\Console\Commands;

use App\Models\AiUsageLog;
use App\Models\AuthenticationLog;
use App\Models\EmailLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PruneMisLogsCommand extends Command
{
    protected $signature = 'mis:prune-logs
                            {--days=90 : Delete log rows older than this many days}
                            {--chunk=1000 : Rows deleted per batch}
                            {--dry-run : Show counts without deleting}';

    protected $description = 'Prune high-volume MIS audit logs (auth, email, AI) past retention';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("Retention cutoff: {$cutoff->toDateTimeString()} ({$days} days)");

        /** @var array<string, Builder<\Illuminate\Database\Eloquent\Model>> $targets */
        $targets = [
            'authentication_logs' => AuthenticationLog::query()->where('logged_at', '<', $cutoff),
            'email_logs' => EmailLog::query()->where('logged_at', '<', $cutoff),
            'ai_usage_logs' => AiUsageLog::query()->where('logged_at', '<', $cutoff),
        ];

        foreach ($targets as $label => $query) {
            $count = (clone $query)->count();
            $this->line("{$label}: {$count} row(s) older than retention");

            if ($dryRun || $count === 0) {
                continue;
            }

            $deleted = 0;

            do {
                $ids = (clone $query)->orderBy('id')->limit($chunk)->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $affected = $query->getModel()->newQuery()->whereIn('id', $ids)->delete();
                $deleted += $affected;
            } while ($affected > 0);

            $this->info("Deleted {$deleted} from {$label}");
        }

        if ($dryRun) {
            $this->comment('Dry run only — no rows deleted.');
        }

        return self::SUCCESS;
    }
}
