<?php

namespace App\Services;

use App\Models\AuthenticationLog;
use App\Models\User;
use App\Support\RequestFingerprint;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuthenticationLogService
{
    public function __construct(
        protected IpGeolocationService $geolocation,
    ) {}

    public function record(
        Request $request,
        string $event,
        bool $success,
        ?Authenticatable $user = null,
        ?string $email = null,
        ?string $failureReason = null,
        string $guard = 'web',
        array $metadata = [],
    ): AuthenticationLog {
        $fingerprint = RequestFingerprint::fromRequest($request);
        $geo = $this->geolocation->lookup($fingerprint['ip_address'], $request);
        $resolvedEmail = $email ?? ($user instanceof User ? $user->email : null);
        $resolvedUserId = $user?->getAuthIdentifier();

        return AuthenticationLog::query()->create([
            'user_id' => is_numeric($resolvedUserId) ? (int) $resolvedUserId : null,
            'email' => $resolvedEmail,
            'event' => $event,
            'success' => $success,
            'failure_reason' => $failureReason,
            'ip_address' => $fingerprint['ip_address'],
            'ip_addresses' => $fingerprint['ip_addresses'],
            'user_agent' => $fingerprint['user_agent'],
            'device_type' => $fingerprint['device_type'],
            'browser' => $fingerprint['browser'],
            'platform' => $fingerprint['platform'],
            'session_id' => $fingerprint['session_id'],
            'guard' => $guard,
            'request_method' => $fingerprint['request_method'],
            'request_path' => $fingerprint['request_path'],
            'referer' => $fingerprint['referer'],
            'accept_language' => $fingerprint['accept_language'],
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
            'timezone' => $geo['timezone'],
            'isp' => $geo['isp'],
            'org' => $geo['org'],
            'asn' => $geo['asn'],
            'is_private_ip' => $geo['is_private_ip'],
            'location_label' => $geo['location_label'],
            'metadata' => array_merge($fingerprint['metadata'], $metadata),
            'logged_at' => now(),
        ]);
    }

    public function logSuccessfulLogin(Request $request, Authenticatable $user, string $guard, bool $remember = false): AuthenticationLog
    {
        return $this->record(
            request: $request,
            event: AuthenticationLog::EVENT_LOGIN_SUCCESS,
            success: true,
            user: $user,
            guard: $guard,
            metadata: ['remember' => $remember],
        );
    }

    public function logFailedLogin(Request $request, string $guard, ?Authenticatable $user = null, ?string $email = null): AuthenticationLog
    {
        $resolvedEmail = $email ?? Arr::get($request->all(), config('fortify.email', 'email'));

        return $this->record(
            request: $request,
            event: AuthenticationLog::EVENT_LOGIN_FAILED,
            success: false,
            user: $user,
            email: is_string($resolvedEmail) ? $resolvedEmail : null,
            failureReason: 'invalid_credentials',
            guard: $guard,
        );
    }

    public function logLogout(Request $request, Authenticatable $user, string $guard): AuthenticationLog
    {
        return $this->record(
            request: $request,
            event: AuthenticationLog::EVENT_LOGOUT,
            success: true,
            user: $user,
            guard: $guard,
        );
    }

    public function logTwoFactorChallenged(Request $request, Authenticatable $user): AuthenticationLog
    {
        return $this->record(
            request: $request,
            event: AuthenticationLog::EVENT_TWO_FACTOR_CHALLENGED,
            success: true,
            user: $user,
            metadata: ['message' => 'Credentials accepted; two-factor authentication required.'],
        );
    }

    public function logTwoFactorFailed(Request $request, Authenticatable $user): AuthenticationLog
    {
        return $this->record(
            request: $request,
            event: AuthenticationLog::EVENT_TWO_FACTOR_FAILED,
            success: false,
            user: $user,
            failureReason: 'invalid_two_factor_code',
        );
    }

    public function logLockout(Request $request): AuthenticationLog
    {
        $email = $request->input(config('fortify.email', 'email'));

        return $this->record(
            request: $request,
            event: AuthenticationLog::EVENT_LOCKOUT,
            success: false,
            email: is_string($email) ? $email : null,
            failureReason: 'rate_limited',
        );
    }
}
