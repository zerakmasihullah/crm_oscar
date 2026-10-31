<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\AuthenticationLog;
use App\Support\EfficientQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticationLogController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'audit.view');

        $search = $request->string('search')->trim()->toString();
        $event = $request->string('event')->trim()->toString();
        $success = $request->string('success')->trim()->toString();
        $perPage = min(100, max(10, $request->integer('per_page', 25)));

        $logs = AuthenticationLog::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    EfficientQuery::applySearch(
                        $builder,
                        $search,
                        prefixColumns: ['email', 'ip_address', 'city', 'country', 'country_code', 'isp', 'browser'],
                        containsColumns: ['location_label', 'region'],
                        exactColumns: ['email', 'ip_address', 'country_code'],
                    );
                    EfficientQuery::orWhereUserMatches($builder, $search);
                });
            })
            ->when($event !== '', fn ($query) => $query->where('event', $event))
            ->when($success === '1', fn ($query) => $query->where('success', true))
            ->when($success === '0', fn ($query) => $query->where('success', false))
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage)
            ->withQueryString()
            ->through(fn (AuthenticationLog $log) => $this->transform($log));

        return Inertia::render('settings/AuthenticationLogs/Index', [
            'logs' => $logs,
            'events' => [
                AuthenticationLog::EVENT_LOGIN_SUCCESS,
                AuthenticationLog::EVENT_LOGIN_FAILED,
                AuthenticationLog::EVENT_LOGOUT,
                AuthenticationLog::EVENT_TWO_FACTOR_CHALLENGED,
                AuthenticationLog::EVENT_TWO_FACTOR_FAILED,
                AuthenticationLog::EVENT_LOCKOUT,
                AuthenticationLog::EVENT_SESSION_REVOKED,
            ],
            'summary' => $this->summary(),
            'filters' => [
                'search' => $search ?: null,
                'event' => $event ?: null,
                'success' => in_array($success, ['0', '1'], true) ? $success : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * @return array{total: int, today: int, failed: int, users: int}
     */
    private function summary(): array
    {
        $today = now()->toDateString();

        return [
            'total' => AuthenticationLog::query()->count(),
            'today' => AuthenticationLog::query()->whereDate('logged_at', $today)->count(),
            'failed' => AuthenticationLog::query()->where('success', false)->count(),
            'users' => (int) AuthenticationLog::query()
                ->whereNotNull('user_id')
                ->selectRaw('count(distinct user_id) as aggregate')
                ->value('aggregate'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AuthenticationLog $log): array
    {
        $user = $log->user;

        return [
            'id' => $log->id,
            'email' => $log->email,
            'event' => $log->event,
            'success' => $log->success,
            'failure_reason' => $log->failure_reason,
            'ip_address' => $log->ip_address,
            'ip_addresses' => $log->ip_addresses,
            'user_agent' => $log->user_agent,
            'device_type' => $log->device_type,
            'browser' => $log->browser,
            'platform' => $log->platform,
            'session_id' => $log->session_id,
            'guard' => $log->guard,
            'request_method' => $log->request_method,
            'request_path' => $log->request_path,
            'referer' => $log->referer,
            'accept_language' => $log->accept_language,
            'country' => $log->country,
            'country_code' => $log->country_code,
            'region' => $log->region,
            'city' => $log->city,
            'latitude' => $log->latitude,
            'longitude' => $log->longitude,
            'timezone' => $log->timezone,
            'isp' => $log->isp,
            'org' => $log->org,
            'asn' => $log->asn,
            'is_private_ip' => $log->is_private_ip,
            'location_label' => $log->location_label,
            'metadata' => $log->metadata,
            'logged_at' => $log->logged_at?->toIso8601String(),
            'user' => $user
                ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
                : null,
        ];
    }
}
