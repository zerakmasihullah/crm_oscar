<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\NewsletterSignup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingAudienceController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'promotions.view');

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $items = NewsletterSignup::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('email', 'like', "%{$search}%");
            })
            ->when($status === 'active', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('unsubscribed', false)->orWhereNull('unsubscribed');
                });
            })
            ->when($status === 'unsubscribed', function ($query) {
                $query->where('unsubscribed', true);
            })
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (NewsletterSignup $row) => [
                'id' => $row->id,
                'email' => $row->email,
                'unsubscribed' => (bool) $row->unsubscribed,
                'unsubscribed_at' => $row->unsubscribed_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        $active = NewsletterSignup::query()
            ->where(function ($query) {
                $query->where('unsubscribed', false)->orWhereNull('unsubscribed');
            })
            ->count();

        $unsubscribed = NewsletterSignup::query()->where('unsubscribed', true)->count();

        return Inertia::render('resources/marketing/Audience', [
            'items' => $this->inertiaPagination($items),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status !== '' ? $status : null,
            ],
            'stats' => [
                'active' => $active,
                'unsubscribed' => $unsubscribed,
                'total' => $active + $unsubscribed,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.create');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        $existing = NewsletterSignup::query()->where('email', $email)->first();

        if ($existing) {
            if ($existing->unsubscribed) {
                $existing->update([
                    'unsubscribed' => false,
                    'unsubscribed_at' => null,
                ]);

                return back()->with('success', __('Resubscribed.'));
            }

            return back()->with('error', __('That email is already on the list.'));
        }

        NewsletterSignup::query()->create([
            'email' => $email,
            'unsubscribed' => false,
        ]);

        return back()->with('success', __('Added to newsletter.'));
    }

    public function toggle(Request $request, NewsletterSignup $signup): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.edit');

        if ($signup->unsubscribed) {
            $signup->update([
                'unsubscribed' => false,
                'unsubscribed_at' => null,
            ]);

            return back()->with('success', __('Resubscribed.'));
        }

        $signup->update([
            'unsubscribed' => true,
            'unsubscribed_at' => now(),
        ]);

        return back()->with('success', __('Unsubscribed.'));
    }

    public function destroy(Request $request, NewsletterSignup $signup): RedirectResponse
    {
        $this->authorizePermission($request, 'promotions.delete');

        $signup->delete();

        return back()->with('success', __('Removed.'));
    }
}
