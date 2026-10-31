<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureSystemFeature;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureValidSessionVersion;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogSlowRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Always register API file; routes inside gate on offline/api flags
            // (OrgSetting may enable offline after boot without redeploy).
            if (file_exists(base_path('routes/api.php'))) {
                Route::middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/api.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'appearance',
            'interface_style',
            'sidebar_state',
            'locale',
        ]);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureUserIsActive::class,
            EnsureValidSessionVersion::class,
            EnsureAdminTwoFactor::class,
            EnsureSystemFeature::class,
            SecurityHeaders::class,
            LogSlowRequests::class,
        ]);

        $middleware->api(append: [
            LogSlowRequests::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'system.feature' => EnsureSystemFeature::class,
            'admin.access' => EnsureAdminAccess::class,
        ]);

        $middleware->statefulApi();
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('backup:run --scheduled')
            ->dailyAt((string) config('backup.auto_time', '02:00'))
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (
                ! $request->expectsJson()
                && ! $request->is('api/*')
                && in_array($status, [403, 404, 500, 503], true)
            ) {
                return Inertia::render('storefront/Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
