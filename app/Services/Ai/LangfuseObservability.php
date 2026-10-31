<?php

namespace App\Services\Ai;

use App\Support\AiSettings;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\LangfuseFacade as Langfuse;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin helpers around axyr/laravel-langfuse for Kit chat + assist.
 */
class LangfuseObservability
{
    public function active(): bool
    {
        return AiSettings::langfuseActive();
    }

    /**
     * Langfuse UI filters by environment. Kit defaults to "default"
     * (Langfuse cloud default) unless LANGFUSE_ENVIRONMENT is set.
     */
    public function environment(): string
    {
        $configured = trim((string) config('langfuse.environment', ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'default';
    }

    public function startChatTrace(?string $userId, ?string $conversationId): void
    {
        if (! $this->active()) {
            return;
        }

        try {
            $trace = Langfuse::trace(new TraceBody(
                name: 'kit-chat',
                userId: $userId,
                sessionId: $conversationId,
                metadata: [
                    'conversation_id' => $conversationId,
                ],
                tags: ['kit-chat'],
                environment: $this->environment(),
            ));
            Langfuse::setCurrentTrace($trace);
        } catch (Throwable $e) {
            Log::debug('Langfuse chat trace skipped', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}  $usage
     */
    public function recordAssist(
        string $action,
        string $model,
        string $inputPreview,
        string $outputPreview,
        array $usage,
        ?string $userId = null,
    ): void {
        if (! $this->active()) {
            return;
        }

        try {
            $trace = Langfuse::trace(new TraceBody(
                name: 'kit-assist',
                userId: $userId,
                metadata: ['action' => $action],
                tags: ['kit-assist', $action],
                environment: $this->environment(),
            ));
            Langfuse::setCurrentTrace($trace);

            $generation = $trace->generation(new GenerationBody(
                name: $action,
                model: $model,
                input: $this->redact($inputPreview),
                environment: $this->environment(),
            ));

            $this->endGeneration($generation, $this->redact($outputPreview), $usage);
        } catch (Throwable $e) {
            Log::debug('Langfuse assist trace skipped', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}  $usage
     */
    protected function endGeneration(LangfuseGeneration $generation, string $output, array $usage): void
    {
        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);

        $generation->end(
            output: $output,
            usage: new Usage(
                input: $prompt,
                output: $completion,
                total: (int) ($usage['total_tokens'] ?? ($prompt + $completion)),
            ),
        );
    }

    public function flush(): void
    {
        if (! $this->active()) {
            return;
        }

        try {
            app(\Axyr\Langfuse\Contracts\EventBatcherInterface::class)->flush();
        } catch (Throwable $e) {
            Log::warning('Langfuse flush failed', ['error' => $e->getMessage()]);
        }
    }

    public function redact(string $value): string
    {
        $max = (int) config('ai.security.langfuse_redact_max_chars', 240);
        $value = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', '[email]', $value) ?? $value;
        $value = preg_replace('/\b(password|token|api[_-]?key|secret)\b\s*[:=]\s*\S+/i', '$1=[redacted]', $value) ?? $value;

        if (mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max).'…';
        }

        return $value;
    }

    /**
     * @return array{ok: bool, message: string, status?: int, environment?: string, trace_id?: string, ui_hint?: string}
     */
    public function ping(): array
    {
        if (! AiSettings::langfuseEnabled()) {
            return ['ok' => false, 'message' => 'Langfuse is turned Off in Settings → AI → Tasks.'];
        }

        if (! AiSettings::langfusePublicKeySet() || ! AiSettings::langfuseSecretKeySet()) {
            return ['ok' => false, 'message' => 'Save both public and secret Langfuse keys first.'];
        }

        AiSettings::applyLangfuseToConfig();

        try {
            $config = app(\Axyr\Langfuse\Config\LangfuseConfig::class);

            if (! $config->enabled || blank($config->publicKey) || blank($config->secretKey)) {
                return ['ok' => false, 'message' => 'Langfuse config is not active after applying settings.'];
            }

            $env = $this->environment();
            $traceId = (string) Str::uuid();
            $eventId = (string) Str::uuid();
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');

            $response = Http::withHeaders([
                'Authorization' => $config->authHeader(),
                'Content-Type' => 'application/json',
            ])
                ->timeout(15)
                ->post($config->ingestionUrl(), [
                    'batch' => [[
                        'id' => $eventId,
                        'type' => 'trace-create',
                        'timestamp' => $timestamp,
                        'body' => [
                            'id' => $traceId,
                            'name' => 'kit-connection-test',
                            'timestamp' => $timestamp,
                            'tags' => ['kit-debug'],
                            'environment' => $env,
                            'metadata' => ['source' => 'settings-test'],
                        ],
                    ]],
                    'metadata' => $config->batchMetadata(1),
                ]);

            $status = $response->status();
            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => $status,
                    'message' => 'Langfuse rejected the request (HTTP '.$status.'). Check keys and Base URL (US: https://us.cloud.langfuse.com).',
                ];
            }

            $errors = $response->json('errors');
            if (is_array($errors) && $errors !== []) {
                $first = $errors[0];
                $msg = is_array($first) ? (string) ($first['message'] ?? 'ingestion error') : 'ingestion error';

                return [
                    'ok' => false,
                    'status' => $status,
                    'message' => 'Langfuse accepted the HTTP call but reported an event error: '.$msg,
                ];
            }

            $ui = rtrim($config->baseUrl, '/').'/project';

            return [
                'ok' => true,
                'status' => $status,
                'message' => 'Connected — traces are already in this project. In Langfuse UI open Tracing → Traces, set Environment to “'.$env.'” (or All), then search “kit-connection-test”. Region: '.$config->baseUrl,
                'environment' => $env,
                'trace_id' => $traceId,
                'ui_hint' => $ui,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Could not reach Langfuse: '.$e->getMessage(),
            ];
        }
    }
}
