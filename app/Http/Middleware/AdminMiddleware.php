<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            abort(401);
        }

        if (! Auth::user()->canAccessAdminPanel()) {
            abort(403, 'You are not authorized to access the admin panel.');
        }

        return $next($request);
    }
}
