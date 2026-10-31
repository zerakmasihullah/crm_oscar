<?php

namespace App\Http\Middleware;

use App\Support\ThemeColors;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance') ?? 'system';
        $interfaceStyle = $request->cookie('interface_style') ?? 'standard';

        if ($appearance === 'glass') {
            $appearance = 'light';
            $interfaceStyle = 'glass';
        }

        View::share('appearance', $appearance);
        View::share('interfaceStyle', $interfaceStyle);
        View::share('themeColors', ThemeColors::current());

        return $next($request);
    }
}
