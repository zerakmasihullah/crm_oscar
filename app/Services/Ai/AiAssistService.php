<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Support\AiPricing;
use App\Support\AiSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiAssistService
{
    /**
     * Run a scoped writing assist. Only the provided text (and optional short
     * instruction) leaves the app — never full forms, user profiles, or keys.
     *
     * Tries the selected/default model first. When failover is enabled, other
     * configured models with API keys are tried in Models list order.
     *
     * @return array{text: string, model_id: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}, duration_ms: int, estimated_cost_usd: float, log_id: int|null, fallback_used: bool}
     */
    public function assist(
        string $action,
        string $text,
        ?string $instruction = null,
        ?string $modelId = null,
        ?int $userId = null,
    ): array {
        $started = hrtime(true);

        if (! AiSettings::enabled()) {
            throw new RuntimeException('AI assist is disabled.');
        }

        // `custom` always powers Ctrl/Cmd+K when AI is on.
        $actionAllowed = in_array($action, AiSettings::actionIds(), true)
            && ($action === 'custom' || AiSettings::featureEnabled($action));

        if (! $actionAllowed) {
            throw new RuntimeException('This AI action is not enabled.');
        }

        $text = $this->normalizeText($text);
        $instruction = $this->normalizeInstruction($instruction);

        try {
            $guard = app(AiRiskGuard::class);
            if ($instruction !== '') {
                $guard->assertSafeUserText(
                    $instruction,
                    $action === 'fill_form' ? AiRiskGuard::SURFACE_FILL_FORM : AiRiskGuard::SURFACE_ASSIST,
                    (int) config('ai.limits.max_instruction_chars', 1000),
                );
            }
            // Field text / form schema: still scan for injection payloads hidden in content.
            if ($text !== '') {
                $guard->assertSafeUserText(
                    $text,
                    $action === 'fill_form' ? AiRiskGuard::SURFACE_FILL_FORM : AiRiskGuard::SURFACE_ASSIST,
                    $action === 'fill_form'
                        ? (int) config('ai.limits.max_form_schema_chars', 6000)
                        : AiSettings::maxInputChars(),
                );
            }
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        if (($action === 'custom' || $action === 'fill_form') && $instruction === '') {
            throw new RuntimeException(
                $action === 'fill_form'
                    ? 'Describe what to put in the form.'
                    : 'A short instruction is required for custom prompts.',
            );
        }

        $allowedFields = [];
        if ($action === 'fill_form') {
            $allowedFields = $this->validateFormSchema($text);
        }

        if ($text === '') {
            if ($action === 'custom' || $action === 'describe' || $action === 'auto') {
                $text = '(empty field — write new content from the instruction)';
            } else {
                throw new RuntimeException('Provide some text to assist with.');
            }
        }

        $maxInput = $action === 'fill_form'
            ? (int) config('ai.limits.max_form_schema_chars', 6000)
            : AiSettings::maxInputChars();
        if (mb_strlen($text) > $maxInput) {
            throw new RuntimeException("Text exceeds the {$maxInput} character limit.");
        }

        $primary = $this->resolveModel($modelId);

        if ($primary === null || ! $primary['api_key_set']) {
            throw new RuntimeException('No AI model with an API key is configured. Set a Default model under Settings → AI → General.');
        }

        $this->assertWithinDailyBudget($userId);

        $system = AiSettings::systemPromptFor($action);
        $system .= "\n\n".AiRiskGuard::agentSafetyAppendix();
        $userContent = $this->buildUserContent($action, $text, $instruction);
        $maxTokens = $action === 'fill_form'
            ? max(
                AiSettings::maxOutputTokens(),
                (int) config('ai.limits.fill_form_max_output_tokens', 800),
            )
            : AiSettings::maxOutputTokens();

        $candidates = [$primary];
        foreach (AiSettings::failoverModels($primary['id']) as $fallback) {
            $candidates[] = $fallback;
        }

        $lastException = null;

        foreach ($candidates as $index => $model) {
            $apiKey = trim((string) (AiSettings::decryptedApiKey($model['id']) ?? ''));
            if ($apiKey === '') {
                continue;
            }

            $fallbackUsed = $index > 0;

            try {
                $result = match (AiSettings::providerDriver($model['provider'])) {
                    'anthropic' => $this->callAnthropic($model, $apiKey, $system, $userContent, $maxTokens),
                    default => $this->callOpenAiCompatible($model, $apiKey, $system, $userContent, $maxTokens),
                };

                $output = $action === 'fill_form'
                    ? $this->sanitizeFormFillOutput($result['text'], $allowedFields)
                    : $this->sanitizeOutput($result['text']);
                $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
                $cost = AiPricing::estimateUsd(
                    $model['provider'],
                    $model['model'],
                    $result['usage']['prompt_tokens'],
                    $result['usage']['completion_tokens'],
                );

                $this->trackUsage($userId, $result['usage']['total_tokens']);

                $logId = $this->recordLog([
                    'user_id' => $userId,
                    'status' => AiUsageLog::STATUS_SUCCESS,
                    'action' => $action,
                    'provider' => $model['provider'],
                    'model_id' => $model['id'],
                    'model_name' => $model['model'],
                    'instruction_preview' => $this->preview($instruction),
                    'input_preview' => $this->preview($text),
                    'output_preview' => $this->preview($output),
                    'prompt_tokens' => $result['usage']['prompt_tokens'],
                    'completion_tokens' => $result['usage']['completion_tokens'],
                    'total_tokens' => $result['usage']['total_tokens'],
                    'estimated_cost_usd' => $cost,
                    'duration_ms' => $durationMs,
                    'error' => $fallbackUsed
                        ? 'Served via failover after primary model failed.'
                        : null,
                ]);

                $observability = app(LangfuseObservability::class);
                $observability->recordAssist(
                    action: $action,
                    model: $model['model'],
                    inputPreview: $this->preview($text !== '' ? $text : $instruction),
                    outputPreview: $this->preview($output),
                    usage: $result['usage'],
                    userId: $userId !== null ? (string) $userId : null,
                );
                $observability->flush();

                return [
                    'text' => $output,
                    'model_id' => $model['id'],
                    'usage' => $result['usage'],
                    'duration_ms' => $durationMs,
                    'estimated_cost_usd' => $cost,
                    'log_id' => $logId,
                    'fallback_used' => $fallbackUsed,
                ];
            } catch (Throwable $exception) {
                $lastException = $exception;
                $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);

                $this->recordLog([
                    'user_id' => $userId,
                    'status' => AiUsageLog::STATUS_FAILED,
                    'action' => $action,
                    'provider' => $model['provider'],
                    'model_id' => $model['id'],
                    'model_name' => $model['model'],
                    'instruction_preview' => $this->preview($instruction),
                    'input_preview' => $this->preview($text),
                    'output_preview' => null,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                    'estimated_cost_usd' => 0,
                    'duration_ms' => $durationMs,
                    'error' => mb_substr($exception->getMessage(), 0, 500),
                ]);

                $hasMore = $index < count($candidates) - 1;
                if (! $hasMore) {
                    break;
                }

                Log::info('AI primary model failed; trying failover', [
                    'failed_model' => $model['id'],
                    'provider' => $model['provider'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw $lastException ?? new RuntimeException('AI assist failed.');
    }

    protected function normalizeText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = trim($text);

        return $text;
    }

    protected function normalizeInstruction(?string $instruction): string
    {
        if ($instruction === null) {
            return '';
        }

        $instruction = str_replace("\0", '', trim($instruction));
        $max = (int) config('ai.limits.max_instruction_chars', 200);

        if (mb_strlen($instruction) > $max) {
            $instruction = mb_substr($instruction, 0, $max);
        }

        return $instruction;
    }

    protected function buildUserContent(string $action, string $text, string $instruction): string
    {
        $guard = app(AiRiskGuard::class);

        if ($action === 'fill_form') {
            $description = $guard->fenceUserContent('form_description', $instruction);
            $fields = $guard->fenceUserContent('form_fields_json', $text);

            return "{$description}\n\n{$fields}\n\nRespond with a single JSON object. Keys MUST be the exact field \"id\" values from the form fields list (never labels). Example: {\"name\":\"Fatima\",\"city\":\"Herat\"}";
        }

        if ($action === 'custom') {
            return $guard->fenceUserContent('instruction', $instruction)
                ."\n\n"
                .$guard->fenceUserContent('field_text', $text);
        }

        if ($instruction !== '') {
            return $guard->fenceUserContent('extra_note', $instruction)
                ."\n\n"
                .$guard->fenceUserContent('field_text', $text);
        }

        return $guard->fenceUserContent('field_text', $text);
    }

    /**
     * @return list<array{id: string, name: string|null, label: string|null}>
     */
    protected function validateFormSchema(string $text): array
    {
        if ($text === '') {
            throw new RuntimeException('Form field list is required.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded) || ! isset($decoded['fields']) || ! is_array($decoded['fields'])) {
            throw new RuntimeException('Form field list must be JSON with a fields array.');
        }

        $maxFields = (int) config('ai.limits.max_form_fields', 40);
        if (count($decoded['fields']) > $maxFields) {
            throw new RuntimeException("Too many form fields (max {$maxFields}).");
        }

        $fields = [];
        foreach ($decoded['fields'] as $field) {
            if (! is_array($field) || blank($field['id'] ?? null)) {
                continue;
            }
            $fields[] = [
                'id' => (string) $field['id'],
                'name' => isset($field['name']) && filled($field['name']) ? (string) $field['name'] : null,
                'label' => isset($field['label']) && filled($field['label']) ? (string) $field['label'] : null,
            ];
        }

        if ($fields === []) {
            throw new RuntimeException('No fillable form fields were provided.');
        }

        return $fields;
    }

    /**
     * @param  list<array{id: string, name: string|null, label: string|null}>  $allowedFields
     */
    protected function sanitizeFormFillOutput(string $text, array $allowedFields): string
    {
        $decoded = $this->decodeJsonObject($text);
        if ($decoded === null) {
            throw new RuntimeException('The AI did not return valid form field JSON.');
        }

        $decoded = $this->normalizeFormFillPayload($decoded);
        $aliases = $this->formFillAliasMap($allowedFields);
        $clean = [];

        foreach ($decoded as $key => $value) {
            $resolvedId = $this->resolveFormFillFieldId((string) $key, $aliases);
            if ($resolvedId === null) {
                continue;
            }

            $normalized = $this->normalizeFormFillValue($value);
            if ($normalized === null) {
                continue;
            }

            $clean[$resolvedId] = $normalized;
        }

        if ($clean === []) {
            throw new RuntimeException('The AI could not map the description to any form fields.');
        }

        return json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonObject(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $match) === 1) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/\[[\s\S]*\]/', $text, $match) === 1) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<string, mixed>
     */
    protected function normalizeFormFillPayload(array $decoded): array
    {
        $map = [];

        foreach (['fields', 'values', 'data', 'result'] as $wrapper) {
            if (! isset($decoded[$wrapper]) || ! is_array($decoded[$wrapper])) {
                continue;
            }

            $inner = $decoded[$wrapper];
            unset($decoded[$wrapper]);

            if (array_is_list($inner)) {
                foreach ($inner as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $key = $row['id'] ?? $row['field'] ?? $row['name'] ?? $row['key'] ?? null;
                    $value = $row['value'] ?? $row['text'] ?? $row['content'] ?? null;
                    if ($key === null || $key === '' || $value === null) {
                        continue;
                    }
                    $map[(string) $key] = $value;
                }
            } else {
                foreach ($inner as $key => $value) {
                    $map[(string) $key] = $value;
                }
            }
        }

        if (array_is_list($decoded)) {
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = $row['id'] ?? $row['field'] ?? $row['name'] ?? $row['key'] ?? null;
                $value = $row['value'] ?? $row['text'] ?? $row['content'] ?? null;
                if ($key === null || $key === '' || $value === null) {
                    continue;
                }
                $map[(string) $key] = $value;
            }

            return $map;
        }

        foreach ($decoded as $key => $value) {
            if (is_array($value) && array_is_list($value) === false && $this->looksLikeNestedFieldRow($value)) {
                continue;
            }
            $map[(string) $key] = $value;
        }

        return $map;
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function looksLikeNestedFieldRow(array $value): bool
    {
        return isset($value['id']) || isset($value['field']) || (isset($value['value']) && (isset($value['name']) || isset($value['label'])));
    }

    /**
     * @param  list<array{id: string, name: string|null, label: string|null}>  $allowedFields
     * @return array<string, string> alias => field id
     */
    protected function formFillAliasMap(array $allowedFields): array
    {
        $aliases = [];

        foreach ($allowedFields as $field) {
            $id = $field['id'];
            foreach ([$id, $field['name'], $field['label']] as $alias) {
                if (! is_string($alias) || $alias === '') {
                    continue;
                }
                $aliases[$alias] = $id;
                $aliases[mb_strtolower($alias)] = $id;
                $slug = $this->formFillSlug($alias);
                if ($slug !== '') {
                    $aliases[$slug] = $id;
                }
            }
        }

        return $aliases;
    }

    /**
     * @param  array<string, string>  $aliases
     */
    protected function resolveFormFillFieldId(string $key, array $aliases): ?string
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        $lower = mb_strtolower($key);
        if (isset($aliases[$lower])) {
            return $aliases[$lower];
        }

        $slug = $this->formFillSlug($key);
        if ($slug !== '' && isset($aliases[$slug])) {
            return $aliases[$slug];
        }

        return null;
    }

    protected function formFillSlug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? $value;

        return trim($value, '_');
    }

    protected function normalizeFormFillValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' ? mb_substr($value, 0, 500) : null;
        }

        return null;
    }

    protected function preview(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $max = (int) config('ai.limits.log_preview_chars', 240);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = trim($value);

        if (mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max).'…';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordLog(array $payload): ?int
    {
        try {
            $log = AiUsageLog::query()->create([
                ...$payload,
                'logged_at' => now(),
            ]);

            return $log->id;
        } catch (Throwable $exception) {
            Log::warning('Failed to write AI usage log', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}  $model
     * @return array{text: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}}
     */
    protected function callOpenAiCompatible(array $model, string $apiKey, string $system, string $userContent, ?int $maxTokens = null): array
    {
        $base = $model['base_url']
            ?: (string) config("ai.providers.{$model['provider']}.default_base_url", 'https://api.openai.com/v1');
        $base = rtrim($base, '/');

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('ai.limits.http_timeout_seconds', 30))
            ->post($base.'/chat/completions', [
                'model' => $model['model'],
                'temperature' => 0.2,
                'max_tokens' => $maxTokens ?? AiSettings::maxOutputTokens(),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if (! $response->successful()) {
            throw $this->providerRejection($model, $response);
        }

        $json = $response->json();
        $text = (string) data_get($json, 'choices.0.message.content', '');
        $usage = [
            'prompt_tokens' => (int) data_get($json, 'usage.prompt_tokens', 0),
            'completion_tokens' => (int) data_get($json, 'usage.completion_tokens', 0),
            'total_tokens' => (int) data_get($json, 'usage.total_tokens', 0),
        ];

        if ($usage['total_tokens'] === 0) {
            $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];
        }

        return ['text' => $text, 'usage' => $usage];
    }

    /**
     * @param  array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}  $model
     * @return array{text: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}}
     */
    protected function callAnthropic(array $model, string $apiKey, string $system, string $userContent, ?int $maxTokens = null): array
    {
        $base = $model['base_url']
            ?: (string) config('ai.providers.anthropic.default_base_url', 'https://api.anthropic.com/v1');
        $base = rtrim($base, '/');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->acceptJson()
            ->timeout((int) config('ai.limits.http_timeout_seconds', 30))
            ->post($base.'/messages', [
                'model' => $model['model'],
                'max_tokens' => $maxTokens ?? AiSettings::maxOutputTokens(),
                'temperature' => 0.2,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if (! $response->successful()) {
            throw $this->providerRejection($model, $response);
        }

        $json = $response->json();
        $parts = data_get($json, 'content', []);
        $text = '';
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'text') {
                    $text .= (string) ($part['text'] ?? '');
                }
            }
        }

        $usage = [
            'prompt_tokens' => (int) data_get($json, 'usage.input_tokens', 0),
            'completion_tokens' => (int) data_get($json, 'usage.output_tokens', 0),
            'total_tokens' => 0,
        ];
        $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];

        return ['text' => $text, 'usage' => $usage];
    }

    protected function sanitizeOutput(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```[a-zA-Z]*\n?|\n?```$/', '', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('The AI returned an empty response.');
        }

        $max = AiSettings::maxInputChars() * 2;
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max);
        }

        return $text;
    }

    protected function assertWithinDailyBudget(?int $userId): void
    {
        $budget = AiSettings::dailyTokenBudget();
        $used = (int) Cache::get($this->budgetCacheKey($userId), 0);

        if ($used >= $budget) {
            throw new RuntimeException('Daily AI token budget has been reached. Try again tomorrow.');
        }
    }

    protected function trackUsage(?int $userId, int $tokens): void
    {
        if ($tokens <= 0) {
            $tokens = 1;
        }

        $key = $this->budgetCacheKey($userId);
        $used = (int) Cache::get($key, 0);
        Cache::put($key, $used + $tokens, now()->endOfDay());
    }

    protected function budgetCacheKey(?int $userId): string
    {
        $day = now()->toDateString();
        $scope = $userId !== null ? "user:{$userId}" : 'anon';

        return "ai.tokens.{$day}.{$scope}";
    }

    /**
     * @return array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}|null
     */
    protected function resolveModel(?string $modelId): ?array
    {
        if (filled($modelId)) {
            $requested = AiSettings::modelById($modelId);
            if ($requested !== null && $requested['api_key_set']) {
                return $requested;
            }
        }

        return AiSettings::defaultModel();
    }

    /**
     * @param  array{id: string, label: string, provider: string, model: string, base_url: string|null, api_key_set: bool}  $model
     */
    protected function providerRejection(array $model, \Illuminate\Http\Client\Response $response): RuntimeException
    {
        $status = $response->status();
        $providerLabel = (string) config("ai.providers.{$model['provider']}.label", $model['provider']);
        $providerMessage = $this->providerErrorMessage($response);

        $this->logProviderFailure($model['provider'], $status, $providerMessage, $model['model']);

        $hint = match (true) {
            $status === 401, $status === 403 => 'API key was rejected. Open Settings → AI → General, re-paste the key for this model, mark it Default, and Save.',
            $status === 404 => 'Model name or base URL looks wrong for this provider. Check the Model field (and Base URL if Custom).',
            $status === 429 => 'Provider rate limit hit. Wait a moment or switch to another model.',
            $status >= 500 => 'Provider is temporarily unavailable. Failover will try the next model if enabled.',
            default => 'Check the model name and API key under Settings → AI → General.',
        };

        $detail = $providerMessage !== '' ? " Provider said: {$providerMessage}" : '';

        return new RuntimeException(
            "{$providerLabel} ({$model['model']}) failed ({$status}). {$hint}{$detail}"
        );
    }

    protected function providerErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $json = $response->json();
        $message = data_get($json, 'error.message')
            ?? data_get($json, 'error')
            ?? data_get($json, 'message');

        if (is_array($message)) {
            $message = data_get($message, 'message') ?? json_encode($message);
        }

        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }

        $message = preg_replace('/sk-[a-zA-Z0-9\-_]{10,}/', 'sk-…', $message) ?? $message;

        return mb_substr($message, 0, 240);
    }

    protected function logProviderFailure(string $provider, int $status, ?string $message = null, ?string $model = null): void
    {
        Log::warning('AI provider request failed', [
            'provider' => $provider,
            'model' => $model,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
