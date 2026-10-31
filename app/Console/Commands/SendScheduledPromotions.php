<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use App\Services\PromotionEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledPromotions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promotions:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled promotion emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled promotions...');

        $promotions = Promotion::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($promotions->isEmpty()) {
            $this->info('No scheduled promotions found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$promotions->count()} promotion(s) to send.");

        $service = app(PromotionEmailService::class);

        foreach ($promotions as $promotion) {
            try {
                $this->info("Queueing promotion: {$promotion->subject}");
                $service->queueSend($promotion);
                $this->info("✓ Queued promotion: {$promotion->subject}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to queue promotion: {$promotion->subject}");
                $this->error("Error: {$e->getMessage()}");
                Log::error('Failed to queue scheduled promotion', [
                    'promotion_id' => $promotion->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Finished processing scheduled promotions.');
        return Command::SUCCESS;
    }
}
