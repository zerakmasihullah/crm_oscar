<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Jobs\SendPromotionTestEmail;
use App\Mail\PromotionEmail;
use App\Models\NewsletterSignup;
use App\Models\Promotion;
use App\Models\User;
use App\Services\PromotionEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function __construct(
        protected PromotionEmailService $promotionEmailService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'promotions.view');

        $search = trim((string) $request->query('search', ''));

        $items = Promotion::query()
            ->with('creator:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('subject', 'like', "%{$search}%");
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('recipient_type'), function ($query) use ($request) {
                $query->where('recipient_type', $request->query('recipient_type'));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Promotion $promotion) => $this->serialize($promotion));

        return Inertia::render('resources/promotions/Index', [
            'items' => $this->inertiaPagination($items),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $request->query('status'),
                'recipient_type' => $request->query('recipient_type'),
            ],
            'templates' => $this->templates(),
            'audience' => $this->audienceCounts(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'promotions.create');

        return Inertia::render('resources/promotions/Create', [
            'templates' => $this->templates(),
            'audience' => $this->audienceCounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.create');

        $validated = $this->validated($request);
        $path = null;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('promotions', 'public');
        }

        $promotion = Promotion::query()->create([
            ...$validated,
            'image' => $path,
            'sent_count' => 0,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('promotions.show', $promotion)
            ->with('success', __('Created.'));
    }

    public function show(Request $request, Promotion $promotion): Response
    {
        $this->authorizePermission($request, 'promotions.view');

        $promotion->load('creator:id,name');

        $recipientsQuery = $promotion->recipients();
        $recipientRows = (clone $recipientsQuery)->count();
        $openedCount = (clone $recipientsQuery)->whereNotNull('opened_at')->count();
        $clickedCount = (clone $recipientsQuery)->whereNotNull('clicked_at')->count();

        $recentRecipients = $promotion->recipients()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($recipient) => [
                'id' => $recipient->id,
                'email' => $recipient->email,
                'status' => $recipient->status,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
                'opened_at' => $recipient->opened_at?->toIso8601String(),
                'clicked_at' => $recipient->clicked_at?->toIso8601String(),
                'attempts' => $recipient->attempts,
                'last_error' => $recipient->last_error,
            ]);

        $progress = $this->promotionEmailService->progress($promotion);

        return Inertia::render('resources/promotions/Show', [
            'item' => $this->serialize($promotion),
            'stats' => [
                'recipient_count' => $this->promotionEmailService->getRecipientCount($promotion),
                'recipient_rows' => $recipientRows,
                'sent_rows' => $progress['sent'],
                'opened_count' => $openedCount,
                'clicked_count' => $clickedCount,
                'open_rate' => $progress['sent'] > 0 ? round(($openedCount / $progress['sent']) * 100, 2) : 0,
                'click_rate' => $progress['sent'] > 0 ? round(($clickedCount / $progress['sent']) * 100, 2) : 0,
                'failed_rows' => $progress['failed'],
                'skipped_rows' => $progress['skipped'],
                'queued_rows' => $progress['queued'],
                'progress_percent' => $progress['percent'],
            ],
            'recent_recipients' => $recentRecipients,
            'templates' => $this->templates(),
        ]);
    }

    public function edit(Request $request, Promotion $promotion): Response
    {
        $this->authorizePermission($request, 'promotions.edit');

        return Inertia::render('resources/promotions/Edit', [
            'item' => $this->serialize($promotion),
            'templates' => $this->templates(),
            'audience' => $this->audienceCounts(),
        ]);
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.edit');

        $validated = $this->validated($request, $promotion);
        $path = $promotion->image;

        if ($request->boolean('remove_image') && $path) {
            Storage::disk('public')->delete($path);
            $path = null;
        }

        if ($request->hasFile('image')) {
            if ($promotion->image) {
                Storage::disk('public')->delete($promotion->image);
            }
            $path = $request->file('image')->store('promotions', 'public');
        }

        $promotion->update([
            ...$validated,
            'image' => $path,
        ]);

        return redirect()
            ->route('promotions.show', $promotion)
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.delete');

        if ($promotion->image) {
            Storage::disk('public')->delete($promotion->image);
        }

        $promotion->delete();

        return redirect()
            ->route('promotions.index')
            ->with('success', __('Deleted.'));
    }

    public function send(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.send');

        if (in_array($promotion->status, ['pending', 'processing'], true)) {
            return back()->with('error', __('This promotion is already sending.'));
        }

        try {
            $this->promotionEmailService->queueSend($promotion);
        } catch (\Throwable $e) {
            return back()->with('error', __('Failed to queue promotion: :message', [
                'message' => $e->getMessage(),
            ]));
        }

        return back()->with('success', __('Campaign queued — emails will send in the background.'));
    }

    public function resend(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.send');

        if (in_array($promotion->status, ['pending', 'processing'], true)) {
            return back()->with('error', __('This promotion is already sending.'));
        }

        try {
            $this->promotionEmailService->queueRetryFailed($promotion);
        } catch (\Throwable $e) {
            return back()->with('error', __('Failed to queue retry: :message', [
                'message' => $e->getMessage(),
            ]));
        }

        return back()->with('success', __('Retrying failed emails in the background. Already-sent addresses are skipped.'));
    }

    public function duplicate(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.create');

        $duplicate = $promotion->replicate();
        $duplicate->subject = $promotion->subject.' (Copy)';
        $duplicate->status = 'draft';
        $duplicate->sent_at = null;
        $duplicate->sent_count = 0;
        $duplicate->scheduled_at = null;
        $duplicate->created_by = $request->user()->id;
        $duplicate->save();

        return redirect()
            ->route('promotions.edit', $duplicate)
            ->with('success', __('Promotion duplicated.'));
    }

    public function preview(Request $request, Promotion $promotion): HttpResponse
    {
        $this->authorizePermission($request, 'promotions.view');

        $recipientEmail = $request->user()->email ?? 'preview@example.com';

        return response(
            (new PromotionEmail($promotion, $recipientEmail))->render()
        );
    }

    public function testEmail(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.send');

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        SendPromotionTestEmail::dispatch($promotion, $validated['email']);

        return back()->with('success', __('Test email queued — check your inbox shortly.'));
    }

    public function schedule(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.edit');

        if ($promotion->status === 'sent') {
            return back()->with('error', __('Sent promotions cannot be scheduled.'));
        }

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $promotion->update([
            'status' => 'scheduled',
            'scheduled_at' => $validated['scheduled_at'],
        ]);

        return back()->with('success', __('Promotion scheduled.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Promotion $promotion = null): array
    {
        $statusRules = ['required', 'in:draft,scheduled,sent,pending,processing'];
        if ($promotion?->status === 'sent') {
            $statusRules = ['nullable', 'in:sent'];
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'template' => ['required', 'string', 'max:255'],
            'recipient_type' => ['required', 'in:users,newsletter,both'],
            'status' => $statusRules,
            'scheduled_at' => ['nullable', 'date'],
        ]);

        unset($validated['image'], $validated['remove_image']);

        if (($validated['status'] ?? null) !== 'scheduled') {
            if (($validated['status'] ?? null) === 'draft') {
                $validated['scheduled_at'] = null;
            }
        }

        if ($promotion?->status === 'sent') {
            $validated['status'] = 'sent';
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'subject' => $promotion->subject,
            'content' => $promotion->content,
            'image' => $promotion->image,
            'image_url' => $this->publicImageUrl($promotion->image),
            'template' => $promotion->template,
            'recipient_type' => $promotion->recipient_type,
            'status' => $promotion->status,
            'scheduled_at' => $promotion->scheduled_at?->toIso8601String(),
            'sent_at' => $promotion->sent_at?->toIso8601String(),
            'sent_count' => $promotion->sent_count,
            'created_by' => $promotion->created_by,
            'creator' => $promotion->relationLoaded('creator') && $promotion->creator
                ? ['id' => $promotion->creator->id, 'name' => $promotion->creator->name]
                : null,
            'created_at' => $promotion->created_at?->toIso8601String(),
            'updated_at' => $promotion->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    protected function templates(): array
    {
        return [
            ['value' => 'classic-banner', 'label' => 'Classic banner', 'hint' => 'Bold header — great for weekend deals'],
            ['value' => 'discount-badge', 'label' => 'Offer badge', 'hint' => 'Highlight a special / flash deal'],
            ['value' => 'product-showcase', 'label' => 'Product focus', 'hint' => 'Feature a pizza or kebab photo'],
            ['value' => 'full-width-image', 'label' => 'Photo first', 'hint' => 'Best when you have a strong image'],
            ['value' => 'newsletter-style', 'label' => 'Newsletter', 'hint' => 'News, hours, or “what’s new”'],
            ['value' => 'minimalist', 'label' => 'Simple', 'hint' => 'Clean and short messages'],
            ['value' => 'modern-card', 'label' => 'Modern card', 'hint' => 'Card layout with clear CTA'],
            ['value' => 'split-layout', 'label' => 'Split layout', 'hint' => 'Text + image side by side'],
            ['value' => 'product-grid', 'label' => 'Menu grid', 'hint' => 'Show a few items at once'],
            ['value' => 'elegant', 'label' => 'Elegant', 'hint' => 'Softer look for occasions'],
        ];
    }

    /**
     * @return array{users: int, newsletter: int, both: int}
     */
    protected function audienceCounts(): array
    {
        $users = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        $newsletter = NewsletterSignup::query()
            ->where(function ($query) {
                $query->where('unsubscribed', false)->orWhereNull('unsubscribed');
            })
            ->count();

        return [
            'users' => $users,
            'newsletter' => $newsletter,
            'both' => $users + $newsletter,
        ];
    }

    protected function publicImageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
