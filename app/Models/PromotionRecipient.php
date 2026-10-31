<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'promotion_id',
        'email',
        'status',
        'sent_at',
        'attempts',
        'last_attempt_at',
        'last_error',
        'opened_at',
        'clicked_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT)->whereNotNull('sent_at');
    }

    public function scopeDeliverable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_QUEUED,
            self::STATUS_FAILED,
        ]);
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT || $this->sent_at !== null;
    }

    public function canAttempt(): bool
    {
        if ($this->isSent()) {
            return false;
        }

        if ($this->status === self::STATUS_SKIPPED) {
            return false;
        }

        return $this->attempts < self::MAX_ATTEMPTS;
    }

    public function markQueued(): void
    {
        $this->forceFill([
            'status' => self::STATUS_QUEUED,
            'last_error' => null,
        ])->save();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => $this->sent_at ?? now(),
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'last_error' => mb_substr($error, 0, 1000),
            'last_attempt_at' => now(),
        ])->save();
    }

    public function markSkipped(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_SKIPPED,
            'last_error' => mb_substr($reason, 0, 1000),
        ])->save();
    }

    public function resetForRetry(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'last_attempt_at' => null,
            'sent_at' => null,
        ])->save();
    }

    public function markAsOpened(): void
    {
        if (! $this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    public function markAsClicked(): void
    {
        if (! $this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
    }
}
