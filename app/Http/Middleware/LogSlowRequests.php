<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowRequests
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $threshold = (int) config('offline.slow_request_ms', 2000);

        if ($durationMs >= $threshold) {
            Log::warning('slow.request', [
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'duration_ms' => $durationMs,
                'status' => $response->getStatusCode(),
                'user_id' => $request->user()?->id,
            ]);
        }

        if ($request->is('api/sync/*') && $durationMs >= (int) config('offline.metrics.slow_ms', 1500)) {
            Log::channel('sync')->warning('sync.slow', [
                'path' => '/'.$request->path(),
                'duration_ms' => $durationMs,
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }
}
