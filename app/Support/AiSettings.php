<?php

namespace App\Support;

use App\Models\OrgSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class AiSettings
{
    public const KEYS = [
        'enabled' => 'ai.enabled',
        'default_model_id' => 'ai.default_model_id',
        'models' => 'ai.models',
        'panel_size' => 'ai.panel_size',
        'failover_enabled' => 'ai.failover_enabled',
        'base_system_prompt' => 'ai.base_system_prompt',
        'action_prompts' => 'ai.action_prompts',
        'features_grammar' => 'ai.features.grammar',
        'features_improve' => 'ai.features.improve',
        'features_shorten' => 'ai.features.shorten',
        'features_expand' => 'ai.features.expand',
        'features_describe' => 'ai.features.describe',
        'features_custom' => 'ai.features.custom',
        'features_fill_form' => 'ai.features.fill_form',
        'features_auto' => 'ai.features.auto',
        'field_edit_enabled' => 'ai.field_edit_enabled',
        'chat_enabled' => 'ai.chat_enabled',
        'chat_allow_create' => 'ai.chat_allow_create',
        'chat_allow_delete' => 'ai.chat_allow_delete',
        'chat_allow_analytics' => 'ai.chat_allow_analytics',
        'langfuse_enabled' => 'ai.langfuse.enabled',
        'langfuse_public_key' => 'ai.langfuse.public_key',
        'langfuse_secret_key' => 'ai.langfuse.secret_key',
        'langfuse_base_url' => 'ai.langfuse.base_url',
        'max_input_chars' => 'ai.max_input_chars',
        'max_output_tokens' => 'ai.max_output_tokens',
        'daily_token_budget' => 'ai.daily_token_budget',
        'disabled_resources' => 'ai.disabled_resources',
        'resource_access' => 'ai.resource_access',
    ];

    /** @var list<string> */
    public const RESOURCE_ACTIONS = ['view', 'create', 'edit', 'delete'];

    /**
     * @return list<string>
     */
    public static function panelSizeIds(): array
    {
        return array_keys(config('ai.panel.sizes', []));
    }

    public static function panelSize(): string
    {
        $default = (string) config('ai.panel.default_size', 'comfortable');
        $value = self::string('panel_size', $default) ?: $default;

        return in_array($value, self::panelSizeIds(), true) ? $value : $default;
    }

    public static function providerDriver(string $provider): string
    {
        $driver = config("ai.providers.{$provider}.driver", 'openai');

        return $driver === 'anthropic' ? 'anthropic' : 'openai';
    }

    public static function providerRequiresBaseUrl(string $provider): bool
    {
        return (bool) config("ai.providers.{$provider}.requires_base_url", false)
            || blank(config("ai.providers.{$provider}.default_base_url"));
    }

    public static function bool(string $key, bool $default): bool
    {
        $raw = OrgSetting::getValue(self::KEYS[$key] ?? $key, null);

        if ($raw === null) {
            return $default;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        return in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default): int
    {
        $raw = OrgSetting::getValue(self::KEYS[$key] ?? $key, null);

        if ($raw === null || $raw === '') {
            return $default;
        }

        return (int) $raw;
    }

    public static function string(string $key, ?string $default = null): ?string
    {
        $raw = OrgSetting::getValue(self::KEYS[$key] ?? $key, null);

        if ($raw === null || $raw === '') {
            return $default;
        }

        return (string) $raw;
    }

    public static function enabled(): bool
    {
        $default = (bool) config('kit.features.ai', false)
            || (bool) config('ai.enabled', false);

        return self::bool('enabled', $default);
    }

    /**
     * General Chat agent (task panel). Defaults on when AI is enabled.
     */
    public static function chatEnabled(): bool
    {
        return self::enabled() && self::bool('chat_enabled', true);
    }

    /**
     * Allow create-style agent tools. Defaults off — must be enabled explicitly.
     */
    public static function chatAllowCreate(): bool
    {
        return self::chatEnabled() && self::bool('chat_allow_create', false);
    }

    /**
     * Allow delete-style agent tools. Defaults off — must be enabled explicitly.
     */
    public static function chatAllowDelete(): bool
    {
        return self::chatEnabled() && self::bool('chat_allow_delete', false);
    }

    /**
     * Allow read-only NL→SQL analytics. Defaults off.
     */
    public static function chatAllowAnalytics(): bool
    {
        return self::chatEnabled() && self::bool('chat_allow_analytics', false);
    }

    /**
     * Org wants Langfuse on (credentials may still be missing).
     */
    public static function langfuseEnabled(): bool
    {
        return self::bool('langfuse_enabled', false);
    }

    public static function langfusePublicKeySet(): bool
    {
        return filled(self::string('langfuse_public_key'));
    }

    public static function langfuseSecretKeySet(): bool
    {
        $raw = OrgSetting::getValue(self::KEYS['langfuse_secret_key'], null);

        return filled($raw);
    }

    public static function langfuseBaseUrl(): string
    {
        $default = (string) config('langfuse.base_url', 'https://cloud.langfuse.com');
        $value = trim((string) (self::string('langfuse_base_url') ?? ''));

        return $value !== '' ? rtrim($value, '/') : $default;
    }

    public static function decryptedLangfuseSecretKey(): ?string
    {
        $encrypted = OrgSetting::getValue(self::KEYS['langfuse_secret_key'], null);
        if (! filled($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ready to send traces (toggle On + both keys present).
     */
    public static function langfuseActive(): bool
    {
        if (! self::langfuseEnabled()) {
            return false;
        }

        $public = trim((string) (self::string('langfuse_public_key') ?? ''));
        $secret = self::decryptedLangfuseSecretKey();

        return $public !== '' && filled($secret);
    }

    /**
     * Ctrl+K / sparkle field editing. Defaults on when AI is enabled.
     */
    public static function fieldEditEnabled(): bool
    {
        return self::enabled() && self::bool('field_edit_enabled', true);
    }

    /**
     * @return list<string>
     */
    public static function providerIds(): array
    {
        return array_keys(config('ai.providers', []));
    }

    /**
     * @return list<string>
     */
    public static function actionIds(): array
    {
        return array_keys(config('ai.actions', []));
    }

    /**
     * @return list<array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}>
     */
    public static function models(): array
    {
        $raw = OrgSetting::getValue(self::KEYS['models'], '[]');

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $models = [];

        foreach ($decoded as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['provider'], $row['model'])) {
                continue;
            }

            $id = (string) $row['id'];
            $provider = (string) $row['provider'];

            if (! in_array($provider, self::providerIds(), true)) {
                continue;
            }

            $models[] = [
                'id' => $id,
                'label' => (string) ($row['label'] ?? $row['model']),
                'provider' => $provider,
                'model' => (string) $row['model'],
                'base_url' => isset($row['base_url']) && filled($row['base_url'])
                    ? (string) $row['base_url']
                    : null,
                'api_key_set' => self::apiKeySet($id),
            ];
        }

        return array_slice($models, 0, (int) config('ai.limits.max_models', 3));
    }

    public static function apiKeySet(string $modelId): bool
    {
        return filled(self::encryptedApiKey($modelId));
    }

    public static function encryptedApiKey(string $modelId): ?string
    {
        $raw = OrgSetting::getValue(self::apiKeyOrgKey($modelId), null);

        return filled($raw) ? (string) $raw : null;
    }

    public static function decryptedApiKey(string $modelId): ?string
    {
        $encrypted = self::encryptedApiKey($modelId);

        if ($encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function apiKeyOrgKey(string $modelId): string
    {
        return 'ai.keys.'.$modelId;
    }

    /**
     * @return array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}|null
     */
    public static function modelById(?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach (self::models() as $model) {
            if ($model['id'] === $id) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @return array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}|null
     */
    public static function defaultModel(): ?array
    {
        $defaultId = self::string('default_model_id');
        $models = self::models();

        if ($defaultId !== null) {
            $match = self::modelById($defaultId);
            if ($match !== null && $match['api_key_set']) {
                return $match;
            }
        }

        foreach ($models as $model) {
            if ($model['api_key_set']) {
                return $model;
            }
        }

        return $models[0] ?? null;
    }

    public static function featureEnabled(string $action): bool
    {
        if (! in_array($action, self::actionIds(), true)) {
            return false;
        }

        $key = 'features_'.$action;

        return self::bool($key, true);
    }

    public static function failoverEnabled(): bool
    {
        $default = (bool) config('ai.failover.enabled_by_default', true);

        return self::bool('failover_enabled', $default);
    }

    public static function baseSystemPrompt(): string
    {
        return trim((string) (self::string('base_system_prompt', '') ?? ''));
    }

    /**
     * Org overrides keyed by action id. Empty string means "use config default".
     *
     * @return array<string, string>
     */
    public static function actionPromptOverrides(): array
    {
        $raw = OrgSetting::getValue(self::KEYS['action_prompts'], '{}');

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach (self::actionIds() as $action) {
            if (! array_key_exists($action, $decoded)) {
                continue;
            }
            $out[$action] = trim((string) $decoded[$action]);
        }

        return $out;
    }

    public static function defaultActionSystemPrompt(string $action): string
    {
        return trim((string) config("ai.actions.{$action}.system", ''));
    }

    /**
     * Effective system prompt for an action (override or default), with optional
     * org-wide base prompt prepended.
     */
    public static function systemPromptFor(string $action): string
    {
        $overrides = self::actionPromptOverrides();
        $body = filled($overrides[$action] ?? null)
            ? (string) $overrides[$action]
            : self::defaultActionSystemPrompt($action);

        $base = self::baseSystemPrompt();
        if ($base !== '') {
            return $base."\n\n".$body;
        }

        return $body;
    }

    /**
     * Models with API keys to try after the primary fails (Models list order).
     *
     * @return list<array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}>
     */
    public static function failoverModels(?string $excludeModelId = null): array
    {
        if (! self::failoverEnabled()) {
            return [];
        }

        $fallbacks = [];
        foreach (self::models() as $model) {
            if (! $model['api_key_set']) {
                continue;
            }
            if ($excludeModelId !== null && $model['id'] === $excludeModelId) {
                continue;
            }
            $fallbacks[] = $model;
        }

        return $fallbacks;
    }

    public static function maxInputChars(): int
    {
        $ceiling = (int) config('ai.limits.max_input_chars', 2500);

        return min(self::int('max_input_chars', $ceiling), $ceiling);
    }

    public static function maxOutputTokens(): int
    {
        $ceiling = (int) config('ai.limits.max_output_tokens', 400);

        return min(self::int('max_output_tokens', $ceiling), $ceiling);
    }

    public static function dailyTokenBudget(): int
    {
        $ceiling = (int) config('ai.limits.daily_token_budget', 80_000);

        return min(self::int('daily_token_budget', $ceiling), $ceiling);
    }

    /**
     * Catalog resource keys with view disabled (AI cannot touch them at all).
     *
     * @return list<string>
     */
    public static function disabledResources(): array
    {
        $disabled = [];

        foreach (self::resourceAccessMap() as $key => $actions) {
            if (! ($actions['view'] ?? true)) {
                $disabled[] = $key;
            }
        }

        return $disabled;
    }

    public static function resourceEnabled(string $key): bool
    {
        return self::resourceActionEnabled($key, 'view');
    }

    public static function resourceActionEnabled(string $key, string $action): bool
    {
        $action = $action === 'update' ? 'edit' : $action;
        if (! in_array($action, self::RESOURCE_ACTIONS, true)) {
            return false;
        }

        $map = self::resourceAccessMap();
        if (! isset($map[$key])) {
            return true;
        }

        return (bool) ($map[$key][$action] ?? true);
    }

    /**
     * Per-resource action map for the settings UI (missing actions default on).
     *
     * @return array<string, array{view: bool, create: bool, edit: bool, delete: bool}>
     */
    public static function resourceAccessMap(): array
    {
        $stored = self::storedResourceAccess();
        $legacyDisabled = self::legacyDisabledResourceKeys();
        $map = [];

        foreach (array_keys(config('ai_resources', []) ?: []) as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $defaults = [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
            ];

            if (isset($legacyDisabled[$key]) && ! isset($stored[$key])) {
                $map[$key] = [
                    'view' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                ];

                continue;
            }

            $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];

            // Back-compat: boolean meant fully on/off.
            if (array_key_exists($key, $stored) && is_bool($stored[$key])) {
                $on = (bool) $stored[$key];
                $map[$key] = [
                    'view' => $on,
                    'create' => $on,
                    'edit' => $on,
                    'delete' => $on,
                ];

                continue;
            }

            foreach (self::RESOURCE_ACTIONS as $action) {
                if (array_key_exists($action, $row)) {
                    $defaults[$action] = (bool) $row[$action];
                }
            }

            if (! $defaults['view']) {
                $defaults['create'] = false;
                $defaults['edit'] = false;
                $defaults['delete'] = false;
            }

            $map[$key] = $defaults;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function storedResourceAccess(): array
    {
        $raw = OrgSetting::getValue(self::KEYS['resource_access'], '{}');

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        // Recover from accidental double-encoding.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, true>
     */
    protected static function legacyDisabledResourceKeys(): array
    {
        $raw = OrgSetting::getValue(self::KEYS['disabled_resources'], '[]');

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $key) {
            if (is_string($key) && $key !== '') {
                $out[$key] = true;
            }
        }

        return $out;
    }

    /**
     * Catalog entries for the Data access settings tab.
     *
     * @return list<array{key: string, label: string, table: string|null, model: string|null}>
     */
    public static function resourceAccessOptions(): array
    {
        $options = [];

        foreach (config('ai_resources', []) ?: [] as $key => $resource) {
            if (! is_string($key) || $key === '' || ! is_array($resource)) {
                continue;
            }

            $table = null;
            $model = null;

            try {
                $table = \App\Support\AiResourceCatalog::tableName([...$resource, 'key' => $key]);
            } catch (\Throwable) {
                $table = is_string($resource['table'] ?? null) ? (string) $resource['table'] : null;
            }

            if (is_string($resource['model'] ?? null)) {
                $model = class_basename((string) $resource['model']);
            }

            $options[] = [
                'key' => $key,
                'label' => (string) ($resource['label'] ?? $key),
                'table' => $table,
                'model' => $model,
            ];
        }

        usort($options, fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    public static function applyToConfig(): void
    {
        try {
            $enabled = self::enabled();

            config([
                'kit.features.ai' => $enabled,
                'ai.enabled' => $enabled,
            ]);

            self::applyLangfuseToConfig();
        } catch (\Throwable) {
            // Database may be unavailable during early install / package discovery.
        }
    }

    /**
     * Push org Langfuse settings into the package config (UI overrides .env when active).
     */
    public static function applyLangfuseToConfig(): void
    {
        if (! self::langfuseActive()) {
            config([
                'langfuse.enabled' => false,
            ]);
            self::refreshLangfuseContainer();

            return;
        }

        config([
            'langfuse.enabled' => true,
            'langfuse.public_key' => trim((string) self::string('langfuse_public_key')),
            'langfuse.secret_key' => (string) self::decryptedLangfuseSecretKey(),
            'langfuse.base_url' => self::langfuseBaseUrl(),
            'langfuse.laravel_ai_enabled' => true,
        ]);
        self::refreshLangfuseContainer();
    }

    /**
     * Drop cached Langfuse bindings so the next resolve picks up org settings.
     */
    protected static function refreshLangfuseContainer(): void
    {
        if (! app()->bound(\Axyr\Langfuse\Config\LangfuseConfig::class)) {
            return;
        }

        app()->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
        app()->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
        app()->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    }

    /**
     * Admin payload — never includes decrypted API keys.
     *
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        $overrides = self::actionPromptOverrides();
        $actionPrompts = [];
        $actionDefaults = [];

        foreach (self::actionIds() as $action) {
            $actionDefaults[$action] = self::defaultActionSystemPrompt($action);
            $actionPrompts[$action] = $overrides[$action] ?? '';
        }

        return [
            'enabled' => self::enabled(),
            'chat_enabled' => self::bool('chat_enabled', true),
            'chat_allow_create' => self::bool('chat_allow_create', false),
            'chat_allow_delete' => self::bool('chat_allow_delete', false),
            'chat_allow_analytics' => self::bool('chat_allow_analytics', false),
            'langfuse_enabled' => self::langfuseEnabled(),
            'langfuse_public_key_set' => self::langfusePublicKeySet(),
            'langfuse_secret_key_set' => self::langfuseSecretKeySet(),
            'langfuse_base_url' => self::langfuseBaseUrl(),
            'default_model_id' => self::string('default_model_id'),
            'models' => self::models(),
            'resource_access' => self::resourceAccessMap(),
            'resources' => self::resourceAccessOptions(),
            'features' => [
                'grammar' => self::featureEnabled('grammar'),
                'improve' => self::featureEnabled('improve'),
                'shorten' => self::featureEnabled('shorten'),
                'expand' => self::featureEnabled('expand'),
                'describe' => self::featureEnabled('describe'),
                'auto' => self::featureEnabled('auto'),
                'custom' => self::featureEnabled('custom'),
                'fill_form' => self::featureEnabled('fill_form'),
            ],
            'failover_enabled' => self::failoverEnabled(),
            'field_edit_enabled' => self::bool('field_edit_enabled', true),
            'base_system_prompt' => self::baseSystemPrompt(),
            'action_prompts' => $actionPrompts,
            'action_prompt_defaults' => $actionDefaults,
            'max_input_chars' => self::maxInputChars(),
            'max_output_tokens' => self::maxOutputTokens(),
            'daily_token_budget' => self::dailyTokenBudget(),
            'panel_size' => self::panelSize(),
            'env_default' => (bool) env('KIT_AI', false),
            'limits_ceiling' => [
                'max_models' => (int) config('ai.limits.max_models', 5),
                'max_input_chars' => (int) config('ai.limits.max_input_chars', 2500),
                'max_output_tokens' => (int) config('ai.limits.max_output_tokens', 400),
                'daily_token_budget' => (int) config('ai.limits.daily_token_budget', 80_000),
                'max_instruction_chars' => (int) config('ai.limits.max_instruction_chars', 1000),
                'max_base_system_prompt_chars' => (int) config('ai.limits.max_base_system_prompt_chars', 1000),
                'max_action_system_prompt_chars' => (int) config('ai.limits.max_action_system_prompt_chars', 2000),
                'max_form_fields' => (int) config('ai.limits.max_form_fields', 40),
                'fill_form_max_output_tokens' => (int) config('ai.limits.fill_form_max_output_tokens', 800),
            ],
            'panel_sizes' => collect(config('ai.panel.sizes', []))
                ->map(fn (array $meta, string $id): array => [
                    'id' => $id,
                    'label' => (string) ($meta['label'] ?? $id),
                    'width' => (int) ($meta['width'] ?? 420),
                    'prompt_rows' => (int) ($meta['prompt_rows'] ?? 3),
                    'result_max_h' => (int) ($meta['result_max_h'] ?? 160),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Compact, non-secret payload for every authenticated page.
     *
     * @return array{
     *     enabled: bool,
     *     default_model_id: string|null,
     *     models: list<array{id: string, label: string, provider: string, model: string}>,
     *     features: array<string, bool>,
     *     actions: list<array{id: string, label: string}>,
     *     max_input_chars: int,
     *     max_instruction_chars: int
     * }
     */
    public static function forClient(): array
    {
        $enabled = self::enabled() && self::defaultModel() !== null && self::defaultModel()['api_key_set'];

        $models = [];
        foreach (self::models() as $model) {
            if (! $model['api_key_set']) {
                continue;
            }
            $models[] = [
                'id' => $model['id'],
                'label' => $model['label'],
                'provider' => $model['provider'],
                'model' => $model['model'],
            ];
        }

        $features = [];
        $actions = [];
        foreach (config('ai.actions', []) as $id => $meta) {
            // Custom is always available for Ctrl+K when AI is on.
            // fill_form is a separate form-wide flow (not a single-field action).
            if ($id === 'fill_form') {
                $features[$id] = $enabled && self::featureEnabled('fill_form');
                continue;
            }

            $on = $enabled && ($id === 'custom' || self::featureEnabled($id));
            $features[$id] = $on;
            if ($on) {
                $actions[] = [
                    'id' => $id,
                    'label' => (string) ($meta['label'] ?? $id),
                ];
            }
        }

        return [
            'enabled' => $enabled && $models !== [],
            'chat_enabled' => $enabled && $models !== [] && self::chatEnabled(),
            'chat_allow_create' => $enabled && $models !== [] && self::chatAllowCreate(),
            'chat_allow_delete' => $enabled && $models !== [] && self::chatAllowDelete(),
            'chat_allow_analytics' => $enabled && $models !== [] && self::chatAllowAnalytics(),
            'default_model_id' => self::defaultModel()['id'] ?? null,
            'models' => $models,
            'features' => $features,
            'actions' => $actions,
            'form_fill_enabled' => $enabled && $models !== [] && self::featureEnabled('fill_form'),
            'field_edit_enabled' => $enabled && $models !== [] && self::fieldEditEnabled(),
            'max_input_chars' => self::maxInputChars(),
            'max_instruction_chars' => (int) config('ai.limits.max_instruction_chars', 1000),
            'max_form_fields' => (int) config('ai.limits.max_form_fields', 40),
            'panel_size' => self::panelSize(),
            'panel_sizes' => collect(config('ai.panel.sizes', []))
                ->map(fn (array $meta, string $id): array => [
                    'id' => $id,
                    'label' => (string) ($meta['label'] ?? $id),
                    'width' => (int) ($meta['width'] ?? 420),
                    'prompt_rows' => (int) ($meta['prompt_rows'] ?? 3),
                    'result_max_h' => (int) ($meta['result_max_h'] ?? 160),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function save(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            OrgSetting::setValue(self::KEYS['enabled'], $input['enabled'] ? '1' : '0');
        }

        if (array_key_exists('chat_enabled', $input)) {
            OrgSetting::setValue(self::KEYS['chat_enabled'], $input['chat_enabled'] ? '1' : '0');
        }

        if (array_key_exists('chat_allow_create', $input)) {
            OrgSetting::setValue(
                self::KEYS['chat_allow_create'],
                $input['chat_allow_create'] ? '1' : '0',
            );
        }

        if (array_key_exists('chat_allow_delete', $input)) {
            OrgSetting::setValue(
                self::KEYS['chat_allow_delete'],
                $input['chat_allow_delete'] ? '1' : '0',
            );
        }

        if (array_key_exists('chat_allow_analytics', $input)) {
            OrgSetting::setValue(
                self::KEYS['chat_allow_analytics'],
                $input['chat_allow_analytics'] ? '1' : '0',
            );
        }

        if (array_key_exists('langfuse_enabled', $input)) {
            OrgSetting::setValue(
                self::KEYS['langfuse_enabled'],
                $input['langfuse_enabled'] ? '1' : '0',
            );
        }

        if (array_key_exists('langfuse_public_key', $input) && filled($input['langfuse_public_key'] ?? null)) {
            OrgSetting::setValue(
                self::KEYS['langfuse_public_key'],
                trim((string) $input['langfuse_public_key']),
            );
        }

        if (array_key_exists('langfuse_secret_key', $input) && filled($input['langfuse_secret_key'] ?? null)) {
            OrgSetting::setValue(
                self::KEYS['langfuse_secret_key'],
                Crypt::encryptString(trim((string) $input['langfuse_secret_key'])),
            );
        }

        if (array_key_exists('langfuse_base_url', $input)) {
            $baseUrl = trim((string) ($input['langfuse_base_url'] ?? ''));
            if ($baseUrl === '') {
                OrgSetting::forget(self::KEYS['langfuse_base_url']);
            } else {
                OrgSetting::setValue(self::KEYS['langfuse_base_url'], rtrim($baseUrl, '/'));
            }
        }

        if (array_key_exists('field_edit_enabled', $input)) {
            OrgSetting::setValue(
                self::KEYS['field_edit_enabled'],
                $input['field_edit_enabled'] ? '1' : '0',
            );
        }

        if (array_key_exists('resource_access', $input) && is_array($input['resource_access'])) {
            $catalog = array_keys(config('ai_resources', []) ?: []);
            $previous = self::storedResourceAccess();
            $stored = [];

            foreach ($catalog as $key) {
                if (! is_string($key) || $key === '') {
                    continue;
                }

                if (! array_key_exists($key, $input['resource_access'])) {
                    // Keep prior flags for keys the client omitted.
                    $existing = $previous[$key] ?? null;
                    if (is_array($existing)) {
                        $stored[$key] = [
                            'view' => (bool) ($existing['view'] ?? true),
                            'create' => (bool) ($existing['create'] ?? true),
                            'edit' => (bool) ($existing['edit'] ?? true),
                            'delete' => (bool) ($existing['delete'] ?? true),
                        ];
                    }

                    continue;
                }

                $row = $input['resource_access'][$key];

                if (is_bool($row)) {
                    $on = $row;
                    $stored[$key] = [
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

                $actions = [
                    'view' => array_key_exists('view', $row) ? (bool) $row['view'] : true,
                    'create' => array_key_exists('create', $row) ? (bool) $row['create'] : true,
                    'edit' => array_key_exists('edit', $row) ? (bool) $row['edit'] : true,
                    'delete' => array_key_exists('delete', $row) ? (bool) $row['delete'] : true,
                ];

                if (! $actions['view']) {
                    $actions['create'] = false;
                    $actions['edit'] = false;
                    $actions['delete'] = false;
                }

                $stored[$key] = $actions;
            }

            OrgSetting::forget(self::KEYS['disabled_resources']);
            // Persist as a JSON string so OrgSetting does not depend on array encoding quirks.
            OrgSetting::setValue(
                self::KEYS['resource_access'],
                json_encode($stored, JSON_THROW_ON_ERROR),
            );
        }

        if (array_key_exists('panel_size', $input) && is_string($input['panel_size'])) {
            $size = $input['panel_size'];
            if (in_array($size, self::panelSizeIds(), true)) {
                OrgSetting::setValue(self::KEYS['panel_size'], $size);
            }
        }

        if (array_key_exists('failover_enabled', $input)) {
            OrgSetting::setValue(
                self::KEYS['failover_enabled'],
                $input['failover_enabled'] ? '1' : '0',
            );
        }

        if (array_key_exists('base_system_prompt', $input)) {
            $base = trim((string) ($input['base_system_prompt'] ?? ''));
            $maxBase = (int) config('ai.limits.max_base_system_prompt_chars', 1000);
            if (mb_strlen($base) > $maxBase) {
                $base = mb_substr($base, 0, $maxBase);
            }
            if ($base === '') {
                OrgSetting::forget(self::KEYS['base_system_prompt']);
            } else {
                OrgSetting::setValue(self::KEYS['base_system_prompt'], $base);
            }
        }

        if (array_key_exists('action_prompts', $input) && is_array($input['action_prompts'])) {
            $maxAction = (int) config('ai.limits.max_action_system_prompt_chars', 2000);
            $prompts = [];
            foreach (self::actionIds() as $action) {
                if (! array_key_exists($action, $input['action_prompts'])) {
                    continue;
                }
                $text = trim((string) $input['action_prompts'][$action]);
                if ($text === '') {
                    continue;
                }
                if (mb_strlen($text) > $maxAction) {
                    $text = mb_substr($text, 0, $maxAction);
                }
                $prompts[$action] = $text;
            }
            OrgSetting::setValue(
                self::KEYS['action_prompts'],
                json_encode($prompts, JSON_THROW_ON_ERROR),
            );
        }

        $features = is_array($input['features'] ?? null) ? $input['features'] : [];
        foreach (self::actionIds() as $action) {
            if (array_key_exists($action, $features)) {
                OrgSetting::setValue(
                    self::KEYS['features_'.$action],
                    $features[$action] ? '1' : '0',
                );
            }
        }

        foreach (['max_input_chars', 'max_output_tokens', 'daily_token_budget'] as $intKey) {
            if (array_key_exists($intKey, $input) && $input[$intKey] !== null) {
                $ceiling = (int) config('ai.limits.'.$intKey, (int) $input[$intKey]);
                $value = min(max(1, (int) $input[$intKey]), $ceiling);
                OrgSetting::setValue(self::KEYS[$intKey], (string) $value);
            }
        }

        $existingIds = collect(self::models())->pluck('id')->all();
        $savedModels = [];
        $maxModels = (int) config('ai.limits.max_models', 3);
        $incoming = is_array($input['models'] ?? null) ? $input['models'] : [];

        foreach (array_slice($incoming, 0, $maxModels) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $provider = (string) ($row['provider'] ?? '');
            $modelName = trim((string) ($row['model'] ?? ''));

            if (! in_array($provider, self::providerIds(), true) || $modelName === '') {
                continue;
            }

            $id = filled($row['id'] ?? null) ? (string) $row['id'] : (string) Str::uuid();
            $label = trim((string) ($row['label'] ?? $modelName)) ?: $modelName;
            $baseUrl = isset($row['base_url']) && filled($row['base_url'])
                ? rtrim((string) $row['base_url'], '/')
                : null;

            $savedModels[] = [
                'id' => $id,
                'label' => $label,
                'provider' => $provider,
                'model' => $modelName,
                'base_url' => $baseUrl,
            ];

            if (array_key_exists('api_key', $row) && filled($row['api_key'])) {
                OrgSetting::setValue(
                    self::apiKeyOrgKey($id),
                    Crypt::encryptString(trim((string) $row['api_key'])),
                );
            }
        }

        OrgSetting::setValue(self::KEYS['models'], json_encode(array_values($savedModels), JSON_THROW_ON_ERROR));

        $keptIds = collect($savedModels)->pluck('id')->all();
        foreach (array_diff($existingIds, $keptIds) as $removedId) {
            OrgSetting::forget(self::apiKeyOrgKey((string) $removedId));
        }

        $defaultId = isset($input['default_model_id']) ? (string) $input['default_model_id'] : '';
        if ($defaultId !== '' && in_array($defaultId, $keptIds, true)) {
            OrgSetting::setValue(self::KEYS['default_model_id'], $defaultId);
        } elseif ($keptIds !== []) {
            OrgSetting::setValue(self::KEYS['default_model_id'], (string) $keptIds[0]);
        } else {
            OrgSetting::forget(self::KEYS['default_model_id']);
        }

        self::applyToConfig();
    }
}
