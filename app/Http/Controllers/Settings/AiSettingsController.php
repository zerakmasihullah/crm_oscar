<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Services\Ai\LangfuseObservability;
use App\Support\AiSettings;
use App\Support\EfficientQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    use AuthorizesMisPermissions;

    public function edit(Request $request): Response
    {
        return $this->configPage($request, 'general');
    }

    public function prompts(Request $request): Response
    {
        return $this->configPage($request, 'prompts');
    }

    public function auto(Request $request): Response
    {
        return redirect()->route('settings.ai.assist');
    }

    public function assist(Request $request): Response
    {
        return $this->configPage($request, 'assist');
    }

    public function tasks(Request $request): Response
    {
        return $this->configPage($request, 'tasks');
    }

    public function data(Request $request): Response
    {
        return $this->configPage($request, 'data');
    }

    public function logs(Request $request): Response
    {
        abort_unless($this->canViewAiLogs($request), 403);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $action = $request->string('action')->trim()->toString();
        $perPage = min(100, max(10, $request->integer('per_page', 25)));

        $baseQuery = AiUsageLog::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    EfficientQuery::applySearch(
                        $builder,
                        $search,
                        prefixColumns: ['model_name', 'error'],
                        containsColumns: ['instruction_preview', 'input_preview', 'output_preview'],
                        exactColumns: ['model_name'],
                    );
                    EfficientQuery::orWhereUserMatches($builder, $search);
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($action !== '', fn ($query) => $query->where('action', $action));

        $logs = (clone $baseQuery)
            ->with('user:id,name,email')
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage)
            ->withQueryString();

        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        $filtered = EfficientQuery::aggregates($baseQuery, [
            'requests' => 'COUNT(*)',
            'success' => "SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END)",
            'failed' => "SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END)",
            'tokens' => 'COALESCE(SUM(total_tokens), 0)',
            'cost_usd' => 'COALESCE(SUM(estimated_cost_usd), 0)',
            'avg_duration_ms' => 'COALESCE(AVG(duration_ms), 0)',
        ]);

        $monthRollup = EfficientQuery::aggregates(
            AiUsageLog::query()->where('logged_at', '>=', $month),
            [
                'tokens' => 'COALESCE(SUM(total_tokens), 0)',
                'cost_usd' => 'COALESCE(SUM(estimated_cost_usd), 0)',
            ],
        );

        $todayRollup = EfficientQuery::aggregates(
            AiUsageLog::query()->where('logged_at', '>=', $today),
            [
                'tokens' => 'COALESCE(SUM(total_tokens), 0)',
                'cost_usd' => 'COALESCE(SUM(estimated_cost_usd), 0)',
            ],
        );

        $summary = [
            'requests' => (int) $filtered['requests'],
            'success' => (int) $filtered['success'],
            'failed' => (int) $filtered['failed'],
            'tokens' => (int) $filtered['tokens'],
            'cost_usd' => round((float) $filtered['cost_usd'], 4),
            'avg_duration_ms' => (int) round((float) $filtered['avg_duration_ms']),
            'today_tokens' => (int) $todayRollup['tokens'],
            'today_cost_usd' => round((float) $todayRollup['cost_usd'], 4),
            'month_tokens' => (int) $monthRollup['tokens'],
            'month_cost_usd' => round((float) $monthRollup['cost_usd'], 4),
        ];

        $byAction = AiUsageLog::query()
            ->select(
                'action',
                DB::raw('count(*) as requests'),
                DB::raw('sum(total_tokens) as tokens'),
                DB::raw('sum(estimated_cost_usd) as cost_usd'),
            )
            ->where('logged_at', '>=', $month)
            ->groupBy('action')
            ->orderByDesc('requests')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'action' => $row->action,
                'requests' => (int) $row->requests,
                'tokens' => (int) $row->tokens,
                'cost_usd' => round((float) $row->cost_usd, 4),
            ])
            ->all();

        return Inertia::render('settings/Ai', [
            'tab' => 'logs',
            'logs' => $logs,
            'summary' => $summary,
            'byAction' => $byAction,
            'statuses' => [AiUsageLog::STATUS_SUCCESS, AiUsageLog::STATUS_FAILED],
            'filters' => [
                'search' => $search ?: null,
                'status' => $status ?: null,
                'action' => $action ?: null,
            ],
            'settings' => $this->canManageAi($request)
                ? AiSettings::forAdmin()
                : null,
            'providers' => $this->providers(),
            'actions' => $this->actions(),
            'canManage' => $this->canManageAi($request),
            'canViewLogs' => true,
        ]);
    }

    protected function configPage(Request $request, string $tab): Response
    {
        abort_unless($this->canManageAi($request), 403);

        return Inertia::render('settings/Ai', [
            'tab' => $tab,
            'settings' => AiSettings::forAdmin(),
            'providers' => $this->providers(),
            'actions' => $this->actions(),
            'canManage' => true,
            'canViewLogs' => $this->canViewAiLogs($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->canManageAi($request), 403);

        $maxModels = (int) config('ai.limits.max_models', 5);
        $ceiling = [
            'max_input_chars' => (int) config('ai.limits.max_input_chars', 2500),
            'max_output_tokens' => (int) config('ai.limits.max_output_tokens', 400),
            'daily_token_budget' => (int) config('ai.limits.daily_token_budget', 80_000),
            'max_base_system_prompt_chars' => (int) config('ai.limits.max_base_system_prompt_chars', 1000),
            'max_action_system_prompt_chars' => (int) config('ai.limits.max_action_system_prompt_chars', 2000),
        ];

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'chat_enabled' => ['required', 'boolean'],
            'chat_allow_create' => ['required', 'boolean'],
            'chat_allow_delete' => ['required', 'boolean'],
            'chat_allow_analytics' => ['required', 'boolean'],
            'langfuse_enabled' => ['required', 'boolean'],
            'langfuse_public_key' => ['nullable', 'string', 'max:255'],
            'langfuse_secret_key' => ['nullable', 'string', 'max:512'],
            'langfuse_base_url' => ['nullable', 'string', 'max:255'],
            'default_model_id' => ['nullable', 'string', 'max:64'],
            'models' => ['nullable', 'array', 'max:'.$maxModels],
            'models.*.id' => ['nullable', 'string', 'max:64'],
            'models.*.label' => ['nullable', 'string', 'max:120'],
            'models.*.provider' => ['required', 'string', Rule::in(AiSettings::providerIds())],
            'models.*.model' => ['required', 'string', 'max:120'],
            'models.*.base_url' => ['nullable', 'string', 'max:255'],
            'models.*.api_key' => ['nullable', 'string', 'max:512'],
            'features' => ['required', 'array'],
            'features.grammar' => ['required', 'boolean'],
            'features.improve' => ['required', 'boolean'],
            'features.shorten' => ['required', 'boolean'],
            'features.expand' => ['required', 'boolean'],
            'features.describe' => ['required', 'boolean'],
            'features.auto' => ['required', 'boolean'],
            'features.custom' => ['required', 'boolean'],
            'features.fill_form' => ['required', 'boolean'],
            'failover_enabled' => ['required', 'boolean'],
            'field_edit_enabled' => ['required', 'boolean'],
            'base_system_prompt' => ['nullable', 'string', 'max:'.$ceiling['max_base_system_prompt_chars']],
            'action_prompts' => ['nullable', 'array'],
            'action_prompts.grammar' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'action_prompts.improve' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'action_prompts.shorten' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'action_prompts.expand' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'action_prompts.describe' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'action_prompts.auto' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'action_prompts.custom' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'action_prompts.fill_form' => ['nullable', 'string', 'max:'.$ceiling['max_action_system_prompt_chars']],
            'max_input_chars' => ['required', 'integer', 'min:100', 'max:'.$ceiling['max_input_chars']],
            'max_output_tokens' => ['required', 'integer', 'min:50', 'max:'.$ceiling['max_output_tokens']],
            'daily_token_budget' => ['required', 'integer', 'min:1000', 'max:'.$ceiling['daily_token_budget']],
            'panel_size' => ['required', 'string', Rule::in(AiSettings::panelSizeIds())],
            'resource_access' => ['nullable', 'array'],
        ]);

        if ($request->exists('resource_access')) {
            $validated['resource_access'] = $this->normalizeResourceAccess(
                is_array($request->input('resource_access'))
                    ? $request->input('resource_access')
                    : [],
            );
        } else {
            unset($validated['resource_access']);
        }

        AiSettings::save($validated);

        return $this->redirectAfterSave($request)->with('success', __('AI settings saved.'));
    }

    public function updateResourceAccess(Request $request): RedirectResponse
    {
        abort_unless($this->canManageAi($request), 403);

        $validated = [
            'resource_access' => $this->normalizeResourceAccess(
                is_array($request->input('resource_access'))
                    ? $request->input('resource_access')
                    : [],
            ),
        ];

        AiSettings::save($validated);

        return redirect()
            ->route('settings.ai.data')
            ->with('success', __('AI settings saved.'));
    }

    protected function redirectAfterSave(Request $request): RedirectResponse
    {
        $tab = $request->input('return_tab');

        return match ($tab) {
            'prompts' => redirect()->route('settings.ai.prompts'),
            'assist' => redirect()->route('settings.ai.assist'),
            'tasks' => redirect()->route('settings.ai.tasks'),
            'data' => redirect()->route('settings.ai.data'),
            default => redirect()->route('settings.ai.edit'),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{view: bool, create: bool, edit: bool, delete: bool}>
     */
    protected function normalizeResourceAccess(array $input): array
    {
        $catalog = array_keys(config('ai_resources', []) ?: []);
        $out = [];

        foreach ($catalog as $key) {
            if (! is_string($key) || $key === '' || ! array_key_exists($key, $input)) {
                continue;
            }

            $row = $input[$key];

            if (is_bool($row)) {
                $on = $row;
                $out[$key] = [
                    'view' => $on,
                    'create' => $on,
                    'edit' => $on,
                    'delete' => $on,
                ];

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $view = $this->toBool($row['view'] ?? true, true);
            $out[$key] = [
                'view' => $view,
                'create' => $view && $this->toBool($row['create'] ?? true, true),
                'edit' => $view && $this->toBool($row['edit'] ?? true, true),
                'delete' => $view && $this->toBool($row['delete'] ?? true, true),
            ];
        }

        return $out;
    }

    protected function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        return $default;
    }

    public function testLangfuse(Request $request, LangfuseObservability $langfuse): JsonResponse
    {
        abort_unless($this->canManageAi($request), 403);

        $result = $langfuse->ping();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    protected function canManageAi(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->can('ai.manage') || $user?->can('settings.manage'));
    }

    protected function canViewAiLogs(Request $request): bool
    {
        $user = $request->user();

        return (bool) (
            $user?->can('ai.view')
            || $user?->can('ai.manage')
            || $user?->can('settings.manage')
            || $user?->can('audit.view')
        );
    }

    /**
     * @return list<array{id: string, label: string, default_base_url: string|null, suggested_model: string|null, requires_base_url: bool, driver: string}>
     */
    protected function providers(): array
    {
        return collect(config('ai.providers', []))
            ->map(fn (array $meta, string $id): array => [
                'id' => $id,
                'label' => (string) ($meta['label'] ?? $id),
                'default_base_url' => $meta['default_base_url'] ?? null,
                'suggested_model' => isset($meta['suggested_model']) ? (string) $meta['suggested_model'] : null,
                'requires_base_url' => (bool) ($meta['requires_base_url'] ?? blank($meta['default_base_url'] ?? null)),
                'driver' => (string) ($meta['driver'] ?? 'openai'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    protected function actions(): array
    {
        return collect(config('ai.actions', []))
            ->map(fn (array $meta, string $id): array => [
                'id' => $id,
                'label' => (string) ($meta['label'] ?? $id),
            ])
            ->values()
            ->all();
    }
}
