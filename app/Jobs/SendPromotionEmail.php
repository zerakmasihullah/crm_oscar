<?php

namespace App\Jobs;

use App\Mail\PromotionEmail;
use App\Models\Promotion;
use App\Models\PromotionRecipient;
use App\Services\PromotionEmailService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPromotionEmail implements ShouldBeUnique, ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [30, 90];

    public function __construct(
        public Promotion $promotion,
        public string $recipientEmail,
        public ?PromotionRecipient $recipient = null,
    ) {}

    public function uniqueId(): string
    {
        return 'promo-mail-'.($this->recipient?->id ?? ($this->promotion->id.'-'.$this->recipientEmail));
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $recipient = $this->recipient
            ? PromotionRecipient::query()->find($this->recipient->id)
            : null;

        if (! $recipient) {
            return;
        }

        // Hard duplicate guard — one successful send per campaign email
        if ($recipient->isSent()) {
            return;
        }

        if ($recipient->status === PromotionRecipient::STATUS_SKIPPED) {
            return;
        }

        if ($recipient->attempts >= PromotionRecipient::MAX_ATTEMPTS) {
            $recipient->markFailed($recipient->last_error ?: 'Max attempts reached.');

            return;
        }

        $weeklyCount = PromotionRecipient::query()
            ->where('email', $recipient->email)
            ->where('status', PromotionRecipient::STATUS_SENT)
            ->where('sent_at', '>=', now()->subWeek())
            ->count();

        if ($weeklyCount >= PromotionEmailService::WEEKLY_LIMIT) {
            $recipient->markSkipped('Weekly email limit reached.');

            return;
        }

        // Another row for same promo+email already sent (legacy duplicates)
        $alreadySent = PromotionRecipient::query()
            ->where('promotion_id', $this->promotion->id)
            ->where('email', $recipient->email)
            ->where('status', PromotionRecipient::STATUS_SENT)
            ->where('id', '!=', $recipient->id)
            ->exists();

        if ($alreadySent) {
            $recipient->markSkipped('Duplicate — already sent for this campaign.');

            return;
        }

        $recipient->increment('attempts');
        $recipient->forceFill([
            'last_attempt_at' => now(),
            'status' => PromotionRecipient::STATUS_QUEUED,
        ])->save();

        try {
            Mail::to($recipient->email)->send(
                new PromotionEmail($this->promotion, $recipient->email, $recipient)
            );
        } catch (Throwable $e) {
            Log::error('Promotion email send failed', [
                'promotion_id' => $this->promotion->id,
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'attempt' => $recipient->attempts,
                'error' => $e->getMessage(),
            ]);

            if ($recipient->attempts >= PromotionRecipient::MAX_ATTEMPTS) {
                $recipient->markFailed($e->getMessage());
            } else {
                $recipient->forceFill([
                    'status' => PromotionRecipient::STATUS_PENDING,
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ])->save();
                throw $e; // allow job retry
            }

            return;
        }

        try {
            $recipient->markSent();
            $this->promotion->increment('sent_count');
        } catch (Throwable $e) {
            Log::warning('Email delivered but status update failed', [
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $recipient = $this->recipient
            ? PromotionRecipient::query()->find($this->recipient->id)
            : null;

        if ($recipient && ! $recipient->isSent()) {
            $recipient->markFailed($exception->getMessage());
        }

        Log::error('Promotion email job exhausted retries', [
            'promotion_id' => $this->promotion->id,
            'email' => $this->recipientEmail,
            'error' => $exception->getMessage(),
        ]);
    }
}
