<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Services\UserSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecurityController extends Controller
{
    /**
     * Security settings live on the profile page.
     */
    public function edit(): RedirectResponse
    {
        return to_route('profile.edit', ['section' => 'security']);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request, UserSessionManager $sessions): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        $sessions->revokeOthers(
            $user,
            $request->hasSession() ? $request->session()->getId() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('profile.edit', ['section' => 'security']);
    }

    /**
     * Revoke all other browser sessions for the current user.
     */
    public function revokeOtherSessions(Request $request, UserSessionManager $sessions): RedirectResponse
    {
        $sessions->revokeOthers(
            $request->user(),
            $request->hasSession() ? $request->session()->getId() : null,
        );

        return to_route('profile.edit', ['section' => 'sessions'])
            ->with('success', __('Other sessions have been signed out.'));
    }
}
