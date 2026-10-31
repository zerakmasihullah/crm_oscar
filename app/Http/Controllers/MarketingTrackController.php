<?php

namespace App\Http\Controllers;

use App\Models\PromotionRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MarketingTrackController extends Controller
{
    public function open(Request $request, PromotionRecipient $recipient): Response
    {
        if (! $request->hasValidSignature()) {
            return $this->pixel();
        }

        if ($recipient->opened_at === null) {
            $recipient->forceFill(['opened_at' => now()])->save();
        }

        return $this->pixel();
    }

    public function click(Request $request, PromotionRecipient $recipient): RedirectResponse
    {
        $target = (string) $request->query('url', config('app.url'));

        if ($request->hasValidSignature() && $recipient->clicked_at === null) {
            $recipient->forceFill([
                'clicked_at' => now(),
                'opened_at' => $recipient->opened_at ?? now(),
            ])->save();
        }

        if (! filter_var($target, FILTER_VALIDATE_URL)) {
            $target = config('app.url');
        }

        return redirect()->away($target);
    }

    public static function openPixelUrl(PromotionRecipient $recipient): string
    {
        return URL::signedRoute(
            'marketing.track.open',
            ['recipient' => $recipient->id],
            now()->addMonths(6),
        );
    }

    public static function clickUrl(PromotionRecipient $recipient, string $url): string
    {
        return URL::signedRoute(
            'marketing.track.click',
            ['recipient' => $recipient->id, 'url' => $url],
            now()->addMonths(6),
        );
    }

    protected function pixel(): Response
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
