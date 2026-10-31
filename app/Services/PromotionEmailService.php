<?php

namespace App\Services;

use App\Jobs\ProcessPromotionSending;
use App\Jobs\SendPromotionEmail;
use App\Models\NewsletterSignup;
use App\Models\Promotion;
use App\Models\PromotionRecipient;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PromotionEmailService
{
    public const WEEKLY_LIMIT = 3;

    public const MODE_SEND = 'send';

    public const MODE_RETRY_FAILED = 'retry_failed';

    /**
     * Fast HTTP path — fan-out runs in a background job.
     */
    public function queueSend(Promotion $promotion): void
    {
        $this->assertNotBusy($promotion);

        $promotion->update([
            'status' => 'processing',
            'sent_at' => null,
        ]);

        ProcessPromotionSending::dispatch($promotion, self::MODE_SEND);
    }

    public function queueRetryFailed(Promotion $promotion): void
    {
        $this->assertNotBusy($promotion);

        $failed = PromotionRecipient::query()
            ->where('promotion_id', $promotion->id)
            ->failed()
            ->count();

        if ($failed === 0) {
            throw new \RuntimeException(__('There are no failed emails to retry.'));
        }

        $promotion->update([
            'status' => 'processing',
            'sent_at' => null,
        ]);

        ProcessPromotionSending::dispatch($promotion, self::MODE_RETRY_FAILED);
    }

    /**
     * @deprecated Use queueRetryFailed — kept for schedule / old callers
     */
    public function queueResend(Promotion $promotion): void
    {
        $this->queueRetryFailed($promotion);
    }

    public function sendPromotion(Promotion $promotion): void
    {
        $this->dispatchCampaign($promotion, self::MODE_SEND);
    }

    public function resendPromotion(Promotion $promotion): void
    {
        $this->dispatchCampaign($promotion, self::MODE_RETRY_FAILED);
    }

    public function dispatchCampaign(Promotion $promotion, string $mode): void
    {
        $lock = Cache::lock('promotion-dispatch-'.$promotion->id, 600);

        if (! $lock->get()) {
            Log::warning('Promotion dispatch already running', ['promotion_id' => $promotion->id]);

            return;
        }

        try {
            $this->runDispatch($promotion, $mode);
        } finally {
            $lock->release();
        }
    }

    protected function runDispatch(Promotion $promotion, string $mode): void
    {
        if ($mode === self::MODE_RETRY_FAILED) {
            PromotionRecipient::query()
                ->where('promotion_id', $promotion->id)
                ->failed()
                ->each(fn (PromotionRecipient $row) => $row->resetForRetry());
        }

        $emails = $this->getRecipients($promotion);

        if ($emails === []) {
            $promotion->update(['status' => 'sent', 'sent_at' => now()]);

            return;
        }

        $jobs = [];
        $oneWeekAgo = now()->subWeek();

        $weeklyCounts = PromotionRecipient::query()
            ->selectRaw('email, COUNT(*) as c')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $oneWeekAgo)
            ->whereIn('email', $emails)
            ->groupBy('email')
            ->pluck('c', 'email');

        foreach (array_chunk($emails, 200) as $chunk) {
            foreach ($chunk as $email) {
                try {
                    try {
                        $recipient = PromotionRecipient::query()->firstOrCreate(
                            [
                                'promotion_id' => $promotion->id,
                                'email' => $email,
                            ],
                            [
                                'status' => PromotionRecipient::STATUS_PENDING,
                                'attempts' => 0,
                            ],
                        );
                    } catch (Throwable $createError) {
                        // Unique race — another worker created the row
                        $recipient = PromotionRecipient::query()
                            ->where('promotion_id', $promotion->id)
                            ->where('email', $email)
                            ->first();

                        if (! $recipient) {
                            throw $createError;
                        }
                    }

                    // Never send twice for the same campaign + email
                    if ($recipient->isSent()) {
                        continue;
                    }

                    if ($mode === self::MODE_SEND && $recipient->status === PromotionRecipient::STATUS_FAILED) {
                        // First send: leave failures for explicit retry
                        continue;
                    }

                    if ($recipient->status === PromotionRecipient::STATUS_SKIPPED
                        && $mode !== self::MODE_RETRY_FAILED) {
                        continue;
                    }

                    $weekly = (int) ($weeklyCounts[$email] ?? 0);
                    if ($weekly >= self::WEEKLY_LIMIT) {
                        $recipient->markSkipped('Weekly email limit reached ('.self::WEEKLY_LIMIT.'/week).');

                        continue;
                    }

                    if (! $recipient->canAttempt() && $recipient->status === PromotionRecipient::STATUS_FAILED) {
                        continue;
                    }

                    if ($recipient->status === PromotionRecipient::STATUS_QUEUED
                        && $recipient->last_attempt_at
                        && $recipient->last_attempt_at->gt(now()->subMinutes(30))) {
                        // Already queued recently — avoid duplicate jobs
                        continue;
                    }

                    $recipient->markQueued();
                    $jobs[] = new SendPromotionEmail($promotion, $email, $recipient);
                } catch (Throwable $e) {
                    Log::error('Failed to prepare promotion recipient', [
                        'promotion_id' => $promotion->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($jobs === []) {
            $this->markCompleteIfDone($promotion->fresh() ?? $promotion);

            return;
        }

        $promotion->update(['status' => 'pending']);
        $promotionId = $promotion->id;

        Bus::batch($jobs)
            ->name('promotion-'.$promotionId)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($promotionId): void {
                $promo = Promotion::query()->find($promotionId);
                if ($promo) {
                    app(self::class)->markCompleteIfDone($promo);
                }
            })
            ->dispatch();

        Log::info('Promotion email batch dispatched', [
            'promotion_id' => $promotion->id,
            'mode' => $mode,
            'job_count' => count($jobs),
        ]);
    }

    /**
     * @return list<string>
     */
    public function getRecipients(Promotion $promotion): array
    {
        $emails = [];

        if ($promotion->recipient_type === 'users' || $promotion->recipient_type === 'both') {
            $emails = array_merge(
                $emails,
                User::query()
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->pluck('email')
                    ->all(),
            );
        }

        if ($promotion->recipient_type === 'newsletter' || $promotion->recipient_type === 'both') {
            $emails = array_merge(
                $emails,
                NewsletterSignup::query()
                    ->where(function ($query) {
                        $query->where('unsubscribed', false)->orWhereNull('unsubscribed');
                    })
                    ->pluck('email')
                    ->all(),
            );
        }

        $unique = [];
        foreach ($emails as $email) {
            $normalized = strtolower(trim((string) $email));
            if ($normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $unique[$normalized] = $normalized;
            }
        }

        $unsubscribed = NewsletterSignup::query()
            ->where('unsubscribed', true)
            ->whereIn('email', array_values($unique))
            ->pluck('email')
            ->map(fn ($e) => strtolower((string) $e))
            ->all();

        foreach ($unsubscribed as $email) {
            unset($unique[$email]);
        }

        return array_values($unique);
    }

    public function getRecipientCount(Promotion $promotion): int
    {
        return count($this->getRecipients($promotion));
    }

    public function markCompleteIfDone(Promotion $promotion): void
    {
        $stillWorking = PromotionRecipient::query()
            ->where('promotion_id', $promotion->id)
            ->whereIn('status', [
                PromotionRecipient::STATUS_PENDING,
                PromotionRecipient::STATUS_QUEUED,
            ])
            ->exists();

        if ($stillWorking) {
            return;
        }

        $hasRows = PromotionRecipient::query()
            ->where('promotion_id', $promotion->id)
            ->exists();

        $sentCount = PromotionRecipient::query()
            ->where('promotion_id', $promotion->id)
            ->where('status', PromotionRecipient::STATUS_SENT)
            ->count();

        $promotion->update([
            'status' => $hasRows ? 'sent' : 'draft',
            'sent_at' => $hasRows ? ($promotion->sent_at ?? now()) : null,
            'sent_count' => $sentCount,
        ]);
    }

    public function progress(Promotion $promotion): array
    {
        $base = PromotionRecipient::query()->where('promotion_id', $promotion->id);

        $total = (clone $base)->count();
        $sent = (clone $base)->where('status', PromotionRecipient::STATUS_SENT)->count();
        $failed = (clone $base)->where('status', PromotionRecipient::STATUS_FAILED)->count();
        $skipped = (clone $base)->where('status', PromotionRecipient::STATUS_SKIPPED)->count();
        $queued = (clone $base)->whereIn('status', [
            PromotionRecipient::STATUS_PENDING,
            PromotionRecipient::STATUS_QUEUED,
        ])->count();

        $done = $sent + $failed + $skipped;

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'queued' => $queued,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    protected function assertNotBusy(Promotion $promotion): void
    {
        if (in_array($promotion->status, ['pending', 'processing'], true)) {
            throw new \RuntimeException(__('This campaign is already sending. Wait for it to finish.'));
        }
    }
}
