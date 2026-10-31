<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSignup;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class MarketingUnsubscribeController extends Controller
{
    public function show(Request $request): View|Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This unsubscribe link is invalid or has expired.');
        }

        $email = strtolower(trim((string) $request->query('email', '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(422, 'Invalid email.');
        }

        $signup = NewsletterSignup::query()->firstOrCreate(
            ['email' => $email],
            ['unsubscribed' => false],
        );

        if (! $signup->unsubscribed) {
            $signup->update([
                'unsubscribed' => true,
                'unsubscribed_at' => now(),
            ]);
        }

        return response()
            ->view('marketing.unsubscribe', ['email' => $email])
            ->header('X-Robots-Tag', 'noindex');
    }

    public static function signedUrl(string $email): string
    {
        return URL::signedRoute(
            'marketing.unsubscribe',
            ['email' => strtolower(trim($email))],
            now()->addYears(2),
        );
    }
}
