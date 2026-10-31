<?php

use App\Jobs\PruneMisLogsJob;
use App\Jobs\PruneTrashJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PruneMisLogsJob(days: 90))
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::job(new PruneTrashJob)
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('uploads:prune')->hourly()->withoutOverlapping();

Schedule::command(\App\Console\Commands\AutoCompleteOrders::class);
Schedule::command('promotions:send-scheduled')->everyMinute()->withoutOverlapping();
// Drain marketing + general jobs frequently so large campaigns don't stall
Schedule::call(function () {
    \Illuminate\Support\Facades\Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--max-jobs' => 100,
        '--max-time' => 50,
        '--timeout' => 60,
    ]);
})->name('process-promotion-queue')->everyMinute()->withoutOverlapping();
