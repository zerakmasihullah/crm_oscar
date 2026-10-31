<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    /**
     * @var list<string>
     */
    protected array $allowedRouteNames = [
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirmation',
        'password.confirm.store',
        'logout',
        'profile.edit',
        'profile.update',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'passkey.register-options',
        'passkey.register',
        'passkey.destroy',
        'well-known.passkeys',
        'locale.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.require_admin_two_factor')) {
            return $next($request);
        }

        if (! Features::enabled(Features::twoFactorAuthentication())) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['Owner', 'Admin'])) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        // Public customer storefront — admins may browse without 2FA gate
        if ($request->routeIs('home', 'storefront.*')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Two-factor authentication must be enabled before continuing.',
                'requires_two_factor_setup' => true,
            ], 403);
        }

        return redirect()
            ->route('profile.edit', ['section' => 'two-factor'])
            ->with('error', __('Two-factor authentication is required for admin accounts.'));
    }

    protected function isAllowed(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, $this->allowedRouteNames, true)) {
            return true;
        }

        return $request->is(
            'settings/security',
            'settings/password',
            'settings/profile',
            'user/confirm-password',
            'user/confirmed-password-status',
            'user/two-factor-authentication',
            'user/confirmed-two-factor-authentication',
            'user/two-factor-qr-code',
            'user/two-factor-secret-key',
            'user/two-factor-recovery-codes',
            'user/passkeys*',
            'logout',
        );
    }
}
