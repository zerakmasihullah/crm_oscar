<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GoogleAuth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): SymfonyRedirectResponse|RedirectResponse
    {
        abort_unless(GoogleAuth::enabled(), 404);

        if ($request->query('intent') === 'storefront') {
            $request->session()->put('oauth_intent', 'storefront');
        } else {
            $request->session()->forget('oauth_intent');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleAuth::enabled(), 404);

        $intent = (string) $request->session()->get('oauth_intent', '');

        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->failed('Google sign-in expired. Please try again.', $intent);
        } catch (Throwable) {
            return $this->failed('Unable to sign in with Google. Please try again.', $intent);
        }

        if (! filled($googleUser->getEmail())) {
            return $this->failed('Google did not return an email address for this account.', $intent);
        }

        $user = $this->resolveUser($googleUser, $intent === 'storefront');

        if ($user === null) {
            return $this->failed(
                $intent === 'storefront' || Features::enabled(Features::registration())
                    ? 'Unable to create your account from Google.'
                    : 'No account exists for this Google email. Please contact an administrator.',
                $intent,
            );
        }

        if (! $user->is_active) {
            return $this->failed('Your account has been disabled.', $intent);
        }

        $request->session()->forget('oauth_intent');

        if ($intent === 'storefront') {
            return $this->completeStorefront($user);
        }

        // Admin Google sign-in: staff only — never promote customers into the CMS.
        if (! $user->canAccessAdminPanel()) {
            return $this->failed(
                'This Google account is not authorized for admin access. Customers should sign in on the website.',
                $intent,
            );
        }

        if ($this->requiresTwoFactor($user)) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => true,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login', config('fortify.home')));
    }

    protected function completeStorefront(User $user): RedirectResponse
    {
        if ((int) $user->role !== User::ROLE_CUSTOMER && (int) $user->role !== User::ROLE_ADMIN) {
            $user->forceFill(['role' => User::ROLE_CUSTOMER])->save();
        }

        if ($this->requiresTwoFactor($user)) {
            return redirect('/?auth_error='.urlencode('This account requires two-factor authentication. Please use email login.'));
        }

        $token = $user->createToken('storefront-google')->plainTextToken;

        return redirect('/?storefront_token='.urlencode($token));
    }

    protected function resolveUser(SocialiteUser $googleUser, bool $forStorefront = false): ?User
    {
        $googleId = (string) $googleUser->getId();
        $email = Str::lower((string) $googleUser->getEmail());

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user) {
            $this->syncProfile($user, $googleUser);

            return $user;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $this->syncProfile($user, $googleUser);

            return $user;
        }

        // Admin Google never auto-creates accounts — staff must be invited first.
        if (! $forStorefront) {
            return null;
        }

        $user = User::query()->create([
            'name' => $googleUser->getName() ?: Str::before($email, '@'),
            'email' => $email,
            'google_id' => $googleId,
            'password' => Hash::make(Str::password(32)),
            'is_active' => true,
            'role' => User::ROLE_CUSTOMER,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        event(new Registered($user));

        return $user;
    }

    protected function syncProfile(User $user, SocialiteUser $googleUser): void
    {
        $updates = [];

        if (blank($user->name) && filled($googleUser->getName())) {
            $updates['name'] = $googleUser->getName();
        }

        if ($user->email_verified_at === null) {
            $updates['email_verified_at'] = now();
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    protected function requiresTwoFactor(User $user): bool
    {
        if (! Features::enabled(Features::twoFactorAuthentication())) {
            return false;
        }

        if (Fortify::confirmsTwoFactorAuthentication()) {
            return filled($user->two_factor_secret) && filled($user->two_factor_confirmed_at);
        }

        return filled($user->two_factor_secret);
    }

    protected function failed(string $message, string $intent = ''): RedirectResponse
    {
        if ($intent === 'storefront' || session('oauth_intent') === 'storefront') {
            session()->forget('oauth_intent');

            return redirect('/?auth_error='.urlencode($message));
        }

        return redirect()
            ->route('login')
            ->with('error', $message);
    }
}
