<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject',
        'content',
        'image',
        'template',
        'recipient_type',
        'status',
        'scheduled_at',
        'sent_at',
        'sent_count',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the user who created this promotion
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all recipients for this promotion
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(PromotionRecipient::class);
    }

    /**
     * Scope a query to only include draft promotions
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include scheduled promotions
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope a query to only include sent promotions
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    /**
     * Get recipients based on recipient_type
     */
    public function getRecipients(): array
    {
        $emails = [];

        if ($this->recipient_type === 'users' || $this->recipient_type === 'both') {
            $userEmails = User::whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->toArray();
            $emails = array_merge($emails, $userEmails);
        }

        if ($this->recipient_type === 'newsletter' || $this->recipient_type === 'both') {
            $newsletterEmails = NewsletterSignup::pluck('email')->toArray();
            $emails = array_merge($emails, $newsletterEmails);
        }

        // Remove duplicates and return unique emails
        return array_unique($emails);
    }
}
