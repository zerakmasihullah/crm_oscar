<?php

namespace App\Http\Controllers\Settings\Concerns;

use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Services\UserSessionManager;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

trait BuildsSecuritySettingsProps
{
    /**
     * @return array<string, mixed>
     */
    protected function securitySettingsProps(
        TwoFactorAuthenticationRequest $request,
        UserSessionManager $sessions,
    ): array {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'sessions' => $sessions->listForUser(
                $request->user(),
                $request->hasSession() ? $request->session()->getId() : null,
            )->values()->all(),
            'canListSessions' => config('session.driver') === 'database',
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return $props;
    }
}
