<?php

namespace App\Http\Middleware;

use App\Support\OfflineSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()');

        $offline = OfflineSettings::enabled();
        $pwa = OfflineSettings::pwaEnabled();

        if ($pwa) {
            $response->headers->set('Service-Worker-Allowed', '/');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $workerSrc = $offline || $pwa
                ? " worker-src 'self';"
                : '';

            $viteOrigins = $this->viteDevOrigins();

            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: blob: https:{$viteOrigins}; media-src 'self' blob: https:; font-src 'self' data: https:{$viteOrigins}; style-src 'self' 'unsafe-inline' https:{$viteOrigins}; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:{$viteOrigins}; connect-src 'self' https: wss: ws:{$viteOrigins};{$workerSrc}",
            );
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Allow Vite HMR assets/scripts in local development.
     */
    private function viteDevOrigins(): string
    {
        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return '';
        }

        $hot = rtrim((string) file_get_contents(Vite::hotFile()));
        $origin = parse_url($hot, PHP_URL_SCHEME).'://'.parse_url($hot, PHP_URL_HOST);

        if ($port = parse_url($hot, PHP_URL_PORT)) {
            $origin .= ':'.$port;
        }

        $origins = array_unique(array_filter([
            $origin,
            'http://127.0.0.1:5173',
            'http://localhost:5173',
            'http://[::1]:5173',
        ]));

        return $origins === [] ? '' : ' '.implode(' ', $origins);
    }
}
