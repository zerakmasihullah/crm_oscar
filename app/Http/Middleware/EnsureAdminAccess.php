<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Block customer (storefront) accounts from the CMS admin area.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->canAccessAdminPanel()) {
            return $next($request);
        }

        // Allow logout so a mistaken session can be cleared
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'You are not authorized to access the admin panel.',
            ], 403);
        }

        return redirect()
            ->route('home')
            ->with('error', 'This account is for ordering on the website only. Admin access is for authorized staff.');
    }
}
