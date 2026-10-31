<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use RuntimeException;
use Throwable;

/**
 * Maps Kit org AI settings (Settings → AI) onto laravel/ai provider config
 * for a single agent prompt, without replacing Kit's writing-assist config.
 */
class AiSdkBridge
{
    /**
     * @return array{provider: string, model: string, model_id: string}
     */
    public static function applyDefaultModel(): array
    {
        $model = AiSettings::defaultModel();

        if ($model === null || ! $model['api_key_set']) {
            throw new RuntimeException('No AI model with an API key is configured.');
        }

        return self::applyModel($model);
    }

    /**
     * Primary model plus failover candidates (Settings → AI order).
     *
     * @return list<array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}>
     */
    public static function candidateModels(): array
    {
        $primary = AiSettings::defaultModel();

        if ($primary === null || ! $primary['api_key_set']) {
            throw new RuntimeException('No AI model with an API key is configured.');
        }

        $candidates = [$primary];
        foreach (AiSettings::failoverModels($primary['id']) as $fallback) {
            $candidates[] = $fallback;
        }

        return $candidates;
    }

    /**
     * @param  array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}  $model
     * @return array{provider: string, model: string, model_id: string}
     */
    public static function applyModel(array $model): array
    {
        $apiKey = AiSettings::decryptedApiKey($model['id']);

        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('API key missing for the selected AI model.');
        }

        $kitProvider = $model['provider'];
        $sdkProvider = self::sdkProviderId($kitProvider);
        $baseUrl = self::baseUrlFor($model, $sdkProvider);

        $driver = match ($sdkProvider) {
            'anthropic' => 'anthropic',
            'gemini' => 'gemini',
            'openai-compatible' => 'openai-compatible',
            'openrouter' => 'openrouter',
            'ollama' => 'ollama',
            'deepseek' => 'deepseek',
            'groq' => 'groq',
            'mistral' => 'mistral',
            default => 'openai',
        };

        $patch = [
            "ai.providers.{$sdkProvider}.driver" => $driver,
            "ai.providers.{$sdkProvider}.key" => $apiKey,
            'ai.default' => $sdkProvider,
        ];

        if ($baseUrl !== '') {
            $patch["ai.providers.{$sdkProvider}.url"] = $baseUrl;
        }

        config($patch);

        try {
            Ai::forgetInstance($sdkProvider);
            Ai::purge($sdkProvider);
        } catch (Throwable $e) {
            Log::debug('AI SDK provider cache clear skipped', [
                'provider' => $sdkProvider,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'provider' => $sdkProvider,
            'model' => $model['model'],
            'model_id' => $model['id'],
        ];
    }

    public static function sdkProviderId(string $kitProvider): string
    {
        // Assist uses Google's OpenAI-compatible URL (no tools). Agent chat needs
        // native Gemini so multi-step tool calls keep thought signatures.
        if ($kitProvider === 'google') {
            return 'gemini';
        }

        $kitDriver = (string) config("ai.providers.{$kitProvider}.driver", 'openai');

        if ($kitProvider === 'anthropic' || $kitDriver === 'anthropic') {
            return 'anthropic';
        }

        return match ($kitProvider) {
            'openai' => 'openai',
            'openrouter' => 'openrouter',
            'ollama' => 'ollama',
            'deepseek' => 'deepseek',
            'groq' => 'groq',
            'mistral' => 'mistral',
            // together, fireworks, openai_compatible, and any other openai-style endpoint
            default => 'openai-compatible',
        };
    }

    /**
     * Resolve the HTTP base URL for the Laravel AI SDK provider.
     *
     * @param  array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}  $model
     */
    public static function baseUrlFor(array $model, ?string $sdkProvider = null): string
    {
        $sdkProvider ??= self::sdkProviderId($model['provider']);

        if ($sdkProvider === 'gemini') {
            $custom = filled($model['base_url'] ?? null) ? rtrim((string) $model['base_url'], '/') : '';

            // Kit stores Google's OpenAI-compat URL for assist — never feed that to native Gemini.
            if ($custom !== '' && ! str_contains($custom, '/openai')) {
                return str_ends_with($custom, '/') ? $custom : $custom.'/';
            }

            return 'https://generativelanguage.googleapis.com/v1beta/';
        }

        if (filled($model['base_url'] ?? null)) {
            return rtrim((string) $model['base_url'], '/');
        }

        return rtrim((string) (config("ai.providers.{$model['provider']}.default_base_url") ?? ''), '/');
    }

    /**
     * Prefer the first actionable failure when failover ends on a misleading auth error.
     *
     * @param  list<Throwable>  $failures
     */
    public static function userMessageFromFailures(array $failures): string
    {
        if ($failures === []) {
            return 'AI chat failed.';
        }

        if (count($failures) === 1) {
            return self::userMessage($failures[0]);
        }

        $nonAuth = [];
        foreach ($failures as $failure) {
            if (! self::looksLikeAuthFailure($failure)) {
                $nonAuth[] = $failure;
            }
        }

        if ($nonAuth !== []) {
            return self::userMessage($nonAuth[0]);
        }

        return self::userMessage($failures[array_key_last($failures)]);
    }

    /**
     * Turn provider exceptions into a short message safe to show in the chat UI.
     */
    public static function userMessage(Throwable $e): string
    {
        $raw = $e->getMessage();

        if (str_contains($raw, 'thought_signature')) {
            return 'This Gemini model rejected a tool call (missing thought signature). Chat uses the native Gemini API — try again, or pick a different default model under Settings → AI.';
        }

        if (str_contains($raw, 'Incorrect API key') || preg_match('/\b401\b/', $raw) === 1) {
            return 'The AI provider rejected the API key. Update it under Settings → AI, or enable a working failover model.';
        }

        if (preg_match('/\b429\b/', $raw) === 1 || str_contains(strtolower($raw), 'rate limit')) {
            return 'The AI provider rate-limited the request. Wait a moment and try again.';
        }

        if (preg_match('/\b404\b/', $raw) === 1) {
            return 'The AI model was not found for this provider. Check the model name in Settings → AI.';
        }

        if ($e instanceof RuntimeException) {
            return $raw;
        }

        return 'AI chat failed: '.mb_substr($raw, 0, 240);
    }

    protected static function looksLikeAuthFailure(Throwable $e): bool
    {
        $raw = $e->getMessage();

        return str_contains($raw, 'Incorrect API key') || preg_match('/\b401\b/', $raw) === 1;
    }
}
