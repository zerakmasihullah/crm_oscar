<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\PromotionRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionRecipientController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'promotion_recipients.view');

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $items = PromotionRecipient::query()
            ->with('promotion:id,subject')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('email', 'like', "%{$search}%")
                        ->orWhereHas('promotion', function ($promo) use ($search) {
                            $promo->where('subject', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('promotion_id'), function ($query) use ($request) {
                $query->where('promotion_id', $request->integer('promotion_id'));
            })
            ->when($status !== '' && in_array($status, [
                PromotionRecipient::STATUS_PENDING,
                PromotionRecipient::STATUS_QUEUED,
                PromotionRecipient::STATUS_SENT,
                PromotionRecipient::STATUS_FAILED,
                PromotionRecipient::STATUS_SKIPPED,
            ], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($request->query('sent') === '1', function ($query) {
                $query->where('status', PromotionRecipient::STATUS_SENT);
            })
            ->when($request->query('sent') === '0', function ($query) {
                $query->where('status', '!=', PromotionRecipient::STATUS_SENT);
            })
            ->when($request->filled('attempts'), function ($query) use ($request) {
                match ($request->query('attempts')) {
                    '0' => $query->where('attempts', 0),
                    '1' => $query->where('attempts', 1),
                    '2+' => $query->where('attempts', '>=', 2),
                    default => $query,
                };
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PromotionRecipient $recipient) => $this->serialize($recipient));

        return Inertia::render('resources/promotion-recipients/Index', [
            'items' => $this->inertiaPagination($items),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'promotion_id' => $request->query('promotion_id'),
                'status' => $status !== '' ? $status : null,
                'sent' => $request->query('sent'),
                'attempts' => $request->query('attempts'),
            ],
            'promotions' => Promotion::query()
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(['id', 'subject'])
                ->map(fn (Promotion $promotion) => [
                    'id' => $promotion->id,
                    'subject' => $promotion->subject,
                ]),
        ]);
    }

    public function show(Request $request, PromotionRecipient $promotionRecipient): Response
    {
        $this->authorizePermission($request, 'promotion_recipients.view');

        $promotionRecipient->load('promotion:id,subject,status,template');

        return Inertia::render('resources/promotion-recipients/Show', [
            'item' => $this->serialize($promotionRecipient),
        ]);
    }

    public function destroy(Request $request, PromotionRecipient $promotionRecipient): RedirectResponse
    {
        $this->authorizePermission($request, 'promotion_recipients.delete');

        $promotionRecipient->delete();

        return redirect()
            ->route('promotion-recipients.index')
            ->with('success', __('Deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(PromotionRecipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'promotion_id' => $recipient->promotion_id,
            'promotion' => $recipient->relationLoaded('promotion') && $recipient->promotion
                ? [
                    'id' => $recipient->promotion->id,
                    'subject' => $recipient->promotion->subject,
                    'status' => $recipient->promotion->status ?? null,
                    'template' => $recipient->promotion->template ?? null,
                ]
                : null,
            'email' => $recipient->email,
            'status' => $recipient->status,
            'sent_at' => $recipient->sent_at?->toIso8601String(),
            'attempts' => $recipient->attempts,
            'last_attempt_at' => $recipient->last_attempt_at?->toIso8601String(),
            'last_error' => $recipient->last_error,
            'opened_at' => $recipient->opened_at?->toIso8601String(),
            'clicked_at' => $recipient->clicked_at?->toIso8601String(),
            'created_at' => $recipient->created_at?->toIso8601String(),
        ];
    }
}
