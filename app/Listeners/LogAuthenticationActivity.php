<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuthenticationLogService;
use App\Services\UserSessionManager;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

class LogAuthenticationActivity
{
    public function __construct(
        protected AuthenticationLogService $authenticationLogService,
        protected UserSessionManager $sessionManager,
        protected Request $request,
    ) {}

    public function handleLogin(Login $event): void
    {
        $this->authenticationLogService->logSuccessfulLogin(
            $this->request,
            $event->user,
            $event->guard,
            $event->remember,
        );

        if ($event->user instanceof User) {
            $this->sessionManager->stampSession($event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials[config('fortify.email', 'email')] ?? null;

        $this->authenticationLogService->logFailedLogin(
            $this->request,
            $event->guard,
            $event->user,
            is_string($email) ? $email : null,
        );
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $this->authenticationLogService->logLogout(
            $this->request,
            $event->user,
            $event->guard,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $this->authenticationLogService->logLockout($event->request);
    }

    public function handleTwoFactorChallenged(TwoFactorAuthenticationChallenged $event): void
    {
        $this->authenticationLogService->logTwoFactorChallenged(
            $this->request,
            $event->user,
        );
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->authenticationLogService->logTwoFactorFailed(
            $this->request,
            $event->user,
        );
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'handleLogin']);
        $events->listen(Failed::class, [self::class, 'handleFailed']);
        $events->listen(Logout::class, [self::class, 'handleLogout']);
        $events->listen(Lockout::class, [self::class, 'handleLockout']);
        $events->listen(TwoFactorAuthenticationChallenged::class, [self::class, 'handleTwoFactorChallenged']);
        $events->listen(TwoFactorAuthenticationFailed::class, [self::class, 'handleTwoFactorFailed']);
    }
}
