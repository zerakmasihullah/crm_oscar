<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\NewsletterSignup;
use App\Models\Promotion;
use App\Models\PromotionRecipient;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can('promotions.view')
                || $request->user()?->can('discounts.view')
                || $request->user()?->can('promotion_recipients.view'),
            403,
        );

        $customers = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        $newsletterActive = NewsletterSignup::query()
            ->where(function ($query) {
                $query->where('unsubscribed', false)->orWhereNull('unsubscribed');
            })
            ->count();

        $newsletterUnsubscribed = NewsletterSignup::query()
            ->where('unsubscribed', true)
            ->count();

        $campaignsTotal = Promotion::query()->count();
        $campaignsDraft = Promotion::query()->where('status', 'draft')->count();
        $campaignsScheduled = Promotion::query()->where('status', 'scheduled')->count();
        $campaignsSending = Promotion::query()->whereIn('status', ['pending', 'processing'])->count();
        $campaignsSent = Promotion::query()->where('status', 'sent')->count();

        $emailsSent30d = PromotionRecipient::query()
            ->where('status', PromotionRecipient::STATUS_SENT)
            ->where('sent_at', '>=', now()->subDays(30))
            ->count();

        $emailsFailed = PromotionRecipient::query()
            ->where('status', PromotionRecipient::STATUS_FAILED)
            ->count();

        $opened30d = PromotionRecipient::query()
            ->whereNotNull('opened_at')
            ->where('sent_at', '>=', now()->subDays(30))
            ->count();

        $sent30d = max($emailsSent30d, 1);
        $openRate = round(($opened30d / $sent30d) * 100, 1);

        $activeDiscounts = Discount::query()->where('is_active', true)->count();

        $recentCampaigns = Promotion::query()
            ->latest()
            ->limit(6)
            ->get(['id', 'subject', 'status', 'recipient_type', 'sent_count', 'sent_at', 'scheduled_at', 'created_at'])
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'subject' => $p->subject,
                'status' => $p->status,
                'recipient_type' => $p->recipient_type,
                'sent_count' => $p->sent_count,
                'sent_at' => $p->sent_at?->toIso8601String(),
                'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        $failedSample = PromotionRecipient::query()
            ->with('promotion:id,subject')
            ->failed()
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (PromotionRecipient $r) => [
                'id' => $r->id,
                'email' => $r->email,
                'last_error' => $r->last_error,
                'promotion' => $r->promotion
                    ? ['id' => $r->promotion->id, 'subject' => $r->promotion->subject]
                    : null,
            ]);

        return Inertia::render('resources/marketing/Index', [
            'stats' => [
                'customers' => $customers,
                'newsletter_active' => $newsletterActive,
                'newsletter_unsubscribed' => $newsletterUnsubscribed,
                'reach' => $customers + $newsletterActive,
                'campaigns_total' => $campaignsTotal,
                'campaigns_draft' => $campaignsDraft,
                'campaigns_scheduled' => $campaignsScheduled,
                'campaigns_sending' => $campaignsSending,
                'campaigns_sent' => $campaignsSent,
                'emails_sent_30d' => $emailsSent30d,
                'emails_failed' => $emailsFailed,
                'open_rate_30d' => $openRate,
                'active_discounts' => $activeDiscounts,
            ],
            'recent_campaigns' => $recentCampaigns,
            'failed_sample' => $failedSample,
        ]);
    }
}
