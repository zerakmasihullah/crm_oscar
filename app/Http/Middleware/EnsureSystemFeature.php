<?php

namespace App\Http\Middleware;

use App\Support\SystemControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemFeature
{
    /**
     * Path prefixes gated by System Control auth toggles.
     *
     * @var array<string, string>
     */
    protected array $pathFeatures = [
        'register' => 'registration',
        'forgot-password' => 'reset_password',
        'reset-password' => 'reset_password',
        'email/verify' => 'email_verification',
        'email/verification-notification' => 'email_verification',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        if ($feature !== null) {
            abort_unless(SystemControl::enabled($feature), 404);

            return $next($request);
        }

        $path = $request->path();

        foreach ($this->pathFeatures as $prefix => $systemFeature) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                abort_unless(SystemControl::enabled($systemFeature), 404);
                break;
            }
        }

        return $next($request);
    }
}
