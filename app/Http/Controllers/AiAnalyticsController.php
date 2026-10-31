<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Models\AiUsageLog;
use App\Services\Ai\AiAnalyticsReportService;
use App\Support\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiAnalyticsController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): Response
    {
        $this->authorizeAnalyticsPage($request);

        $user = $request->user();
        $aiOn = AiSettings::enabled();
        $analyticsOn = AiSettings::chatAllowAnalytics();
        $canGenerate = (bool) $user?->can('ai.analytics');

        $examples = [];
        if ($user?->can('users.view')) {
            $examples[] = ['label' => 'Users', 'prompt' => 'Overview of users: totals, active vs inactive, and roles.'];
        }
        if ($user?->can('field_reports.view')) {
            $examples[] = ['label' => 'Field reports', 'prompt' => 'Field reports by status and recent activity.'];
        }
        if ($user?->can('ai.view') || $user?->can('ai.manage') || $user?->can('settings.manage') || $user?->can('audit.view')) {
            $examples[] = ['label' => 'AI usage', 'prompt' => 'AI usage this month: calls, tokens, and estimated cost.'];
        }
        if ($user?->can('audit.view')) {
            $examples[] = ['label' => 'Logins', 'prompt' => 'Login activity for users'];
        }
        if ($examples === []) {
            $examples[] = ['label' => 'Help', 'prompt' => 'What can you analyze for me?'];
        }

        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        $usageBase = AiUsageLog::query()
            ->where('user_id', $user?->id)
            ->where('action', 'analytics_report')
            ->where('status', AiUsageLog::STATUS_SUCCESS);

        return Inertia::render('AiAnalytics', [
            'canGenerate' => $aiOn && $analyticsOn && $canGenerate,
            'analyticsEnabled' => $analyticsOn,
            'aiEnabled' => $aiOn,
            'setupUrl' => '/settings/ai/tasks',
            'examples' => $examples,
            'usage' => [
                'today_requests' => (int) (clone $usageBase)->where('logged_at', '>=', $today)->count(),
                'today_tokens' => (int) (clone $usageBase)->where('logged_at', '>=', $today)->sum('total_tokens'),
                'today_cost_usd' => round((float) (clone $usageBase)->where('logged_at', '>=', $today)->sum('estimated_cost_usd'), 4),
                'month_requests' => (int) (clone $usageBase)->where('logged_at', '>=', $month)->count(),
                'month_tokens' => (int) (clone $usageBase)->where('logged_at', '>=', $month)->sum('total_tokens'),
                'month_cost_usd' => round((float) (clone $usageBase)->where('logged_at', '>=', $month)->sum('estimated_cost_usd'), 4),
            ],
        ]);
    }

    public function generate(Request $request, AiAnalyticsReportService $reports): JsonResponse|StreamedResponse
    {
        abort_unless($request->user()?->can('ai.analytics'), 403);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'mode' => ['sometimes', 'string', 'in:efficient,detailed'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
        ]);
        $mode = (string) ($validated['mode'] ?? 'efficient');
        $history = is_array($validated['history'] ?? null) ? $validated['history'] : [];

        $wantsStream = $request->boolean('stream')
            || str_contains((string) $request->header('Accept'), 'text/event-stream');

        if (! $wantsStream) {
            return $this->generateJson($request, $reports, $validated['prompt'], $mode, $history);
        }

        return $this->generateStream($request, $reports, $validated['prompt'], $mode, $history);
    }

    protected function generateJson(
        Request $request,
        AiAnalyticsReportService $reports,
        string $prompt,
        string $mode,
        array $history,
    ): JsonResponse
    {
        try {
            $report = $reports->generate($request->user(), $prompt, mode: $mode, history: $history);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $this->publicErrorMessage($exception),
            ], 500);
        }

        return response()->json(
            ($report['kind'] ?? 'report') === 'chat'
                ? $report
                : ['report' => $report],
        );
    }

    protected function generateStream(
        Request $request,
        AiAnalyticsReportService $reports,
        string $prompt,
        string $mode,
        array $history,
    ): StreamedResponse
    {
        $user = $request->user();

        return response()->stream(function () use ($reports, $user, $prompt, $mode, $history): void {
            $send = function (string $event, array $payload): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                @flush();
            };

            try {
                $result = $reports->generate(
                    $user,
                    $prompt,
                    function (array $step) use ($send): void {
                        $send('step', $step);
                    },
                    $mode,
                    $history,
                );
                if (($result['kind'] ?? 'report') === 'chat') {
                    $send('done', $result);
                } else {
                    $send('done', ['kind' => 'report', 'report' => $result]);
                }
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $send('error', [
                    'message' => $exception->getMessage(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $send('error', [
                    'message' => $this->publicErrorMessage($exception),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function publicErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'SQLSTATE') || str_contains(strtolower($message), 'sql syntax')) {
            return 'A generated query was not valid for this database. Try again with a simpler prompt.';
        }

        return 'Analytics report failed. Try again or simplify your prompt.';
    }

    protected function authorizeAnalyticsPage(Request $request): void
    {
        abort_unless($request->user(), 401);

        $user = $request->user();

        abort_unless(
            $user->can('ai.view') || $user->can('ai.analytics'),
            403,
        );
    }
}
