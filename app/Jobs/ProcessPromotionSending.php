<?php

namespace App\Jobs;

use App\Models\Promotion;
use App\Services\PromotionEmailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPromotionSending implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(
        public Promotion $promotion,
        public string $type = PromotionEmailService::MODE_SEND,
    ) {}

    public function uniqueId(): string
    {
        return 'promo-process-'.$this->promotion->id;
    }

    public function handle(PromotionEmailService $service): void
    {
        $promotion = $this->promotion->fresh();

        if (! $promotion) {
            return;
        }

        try {
            $service->dispatchCampaign(
                $promotion,
                $this->type === PromotionEmailService::MODE_RETRY_FAILED
                    ? PromotionEmailService::MODE_RETRY_FAILED
                    : PromotionEmailService::MODE_SEND,
            );
        } catch (Throwable $e) {
            Log::error('Failed to process promotion sending', [
                'promotion_id' => $promotion->id,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            $promotion->update(['status' => 'draft']);

            throw $e;
        }
    }
}
