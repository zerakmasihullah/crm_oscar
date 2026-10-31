<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidSessionVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $storedVersion = (int) $request->session()->get('auth.session_version', 0);
        $currentVersion = (int) $user->session_version;

        // First authenticated request after deploy / upgrade: stamp current version.
        if ($storedVersion === 0) {
            $request->session()->put('auth.session_version', $currentVersion);

            return $next($request);
        }

        if ($storedVersion !== $currentVersion) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', __('Your session has expired. Please sign in again.'));
        }

        return $next($request);
    }
}
