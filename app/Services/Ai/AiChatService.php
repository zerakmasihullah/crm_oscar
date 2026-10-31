<?php

namespace App\Services\Ai;

use App\Ai\Agents\KitAgent;
use App\Models\AiConversationMessage;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\AiChatActivity;
use App\Support\AiChatContext;
use App\Support\AiPricing;
use App\Support\AiSdkBridge;
use App\Support\AiSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\AgentResponse;
use RuntimeException;
use Throwable;

class AiChatService
{
    /**
     * @return array{
     *     conversations: list<array{id: string, title: string, updated_at: string|null, preview: string|null}>,
     *     next_cursor: string|null,
     *     has_more: bool
     * }
     */
    public function conversations(
        User $user,
        ?string $search = null,
        ?string $cursor = null,
        int $limit = 12,
    ): array {
        $this->assertChatAllowed($user);

        $limit = max(1, min(30, $limit));
        $conversationsTable = (string) config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = (string) config('ai.conversations.tables.messages', 'agent_conversation_messages');

        $query = $user->conversations()
            ->select([
                "{$conversationsTable}.id",
                "{$conversationsTable}.title",
                "{$conversationsTable}.updated_at",
            ])
            ->orderByDesc("{$conversationsTable}.updated_at")
            ->orderByDesc("{$conversationsTable}.id");

        $search = filled($search) ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($builder) use ($term, $conversationsTable, $messagesTable): void {
                $builder->where("{$conversationsTable}.title", 'like', $term)
                    ->orWhereExists(function ($sub) use ($term, $conversationsTable, $messagesTable): void {
                        $sub->selectRaw('1')
                            ->from($messagesTable)
                            ->whereColumn("{$messagesTable}.conversation_id", "{$conversationsTable}.id")
                            ->where("{$messagesTable}.role", 'user')
                            ->where("{$messagesTable}.content", 'like', $term);
                    });
            });
        }

        if (filled($cursor)) {
            $parts = explode('|', (string) $cursor, 2);
            if (count($parts) === 2 && filled($parts[0]) && filled($parts[1])) {
                $cursorAt = $parts[0];
                $cursorId = $parts[1];
                $query->where(function ($builder) use ($conversationsTable, $cursorAt, $cursorId): void {
                    $builder->where("{$conversationsTable}.updated_at", '<', $cursorAt)
                        ->orWhere(function ($inner) use ($conversationsTable, $cursorAt, $cursorId): void {
                            $inner->where("{$conversationsTable}.updated_at", '=', $cursorAt)
                                ->where("{$conversationsTable}.id", '<', $cursorId);
                        });
                });
            }
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $ids = $rows->pluck('id')->all();
        $previews = [];

        if ($ids !== []) {
            $previewRows = ConversationMessage::query()
                ->select(['conversation_id', 'content', 'created_at'])
                ->whereIn('conversation_id', $ids)
                ->where('role', 'user')
                ->orderByDesc('created_at')
                ->get()
                ->unique('conversation_id');

            foreach ($previewRows as $previewRow) {
                $content = trim((string) $previewRow->content);
                if ($content !== '') {
                    $previews[(string) $previewRow->conversation_id] = mb_substr($content, 0, 100);
                }
            }
        }

        $conversations = $rows->map(function (Conversation $conversation) use ($previews): array {
            return [
                'id' => (string) $conversation->id,
                'title' => filled($conversation->title)
                    ? (string) $conversation->title
                    : 'Chat',
                'updated_at' => optional($conversation->updated_at)?->toIso8601String(),
                'preview' => $previews[(string) $conversation->id] ?? null,
            ];
        })->values()->all();

        $last = $rows->last();
        $nextCursor = null;
        if ($hasMore && $last !== null) {
            $nextCursor = optional($last->updated_at)?->format('Y-m-d H:i:s').'|'.$last->id;
        }

        return [
            'conversations' => $conversations,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * @return array{
     *     conversation_id: string,
     *     title: string,
     *     messages: list<array{id: string, role: string, content: string, pending_approvals?: list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>}>
     * }
     */
    public function conversation(User $user, string $conversationId): array
    {
        $this->assertChatAllowed($user);

        $conversation = $user->conversations()->whereKey($conversationId)->first();

        if ($conversation === null) {
            throw new RuntimeException('Conversation not found.');
        }

        $records = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        $messages = [];
        $usage = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost_usd' => 0.0,
        ];

        foreach ($records as $record) {
            $role = (string) $record->role;

            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            if ($role === 'assistant') {
                $storedUsage = is_array($record->usage) ? $record->usage : [];
                $prompt = (int) ($storedUsage['prompt_tokens'] ?? 0);
                $completion = (int) ($storedUsage['completion_tokens'] ?? 0);
                $usage['prompt_tokens'] += $prompt;
                $usage['completion_tokens'] += $completion;
                $usage['total_tokens'] += $prompt + $completion;

                $meta = is_array($record->meta) ? $record->meta : [];
                $provider = (string) ($meta['provider'] ?? '');
                $model = (string) ($meta['model'] ?? '');
                if ($provider === '' || $model === '') {
                    $default = AiSettings::defaultModel();
                    $provider = $provider !== '' ? $provider : (string) ($default['provider'] ?? '');
                    $model = $model !== '' ? $model : (string) ($default['model'] ?? '');
                }
                $usage['estimated_cost_usd'] += AiPricing::estimateUsd($provider, $model, $prompt, $completion);
            }

            $content = trim((string) $record->content);
            $pending = [];

            if ($role === 'assistant') {
                $state = is_array($record->approval_state) ? $record->approval_state : [];
                $pendingMap = is_array($state['pending'] ?? null) ? $state['pending'] : [];
                $toolCalls = is_array($record->tool_calls) ? $record->tool_calls : [];

                foreach ($toolCalls as $toolCall) {
                    if (! is_array($toolCall) || ! isset($toolCall['id'], $toolCall['name'])) {
                        continue;
                    }

                    $id = (string) $toolCall['id'];
                    if (! array_key_exists($id, $pendingMap)) {
                        continue;
                    }

                    $pending[] = [
                        'id' => $id,
                        'tool' => (string) $toolCall['name'],
                        'arguments' => is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : [],
                        'reason' => is_string($pendingMap[$id] ?? null) ? $pendingMap[$id] : null,
                    ];
                }
            }

            if ($content === '' && $pending === []) {
                continue;
            }

            $toolCalls = is_array($record->tool_calls) ? $record->tool_calls : [];
            $toolResults = is_array($record->tool_results) ? $record->tool_results : [];
            $steps = $role === 'assistant'
                ? AiChatActivity::stepsFromStored($toolCalls, $toolResults, $pending)
                : [];

            $linkBag = [];
            if ($role === 'assistant') {
                foreach (AiChatActivity::extractMarkdownLinks($content) as $link) {
                    $linkBag[] = $link;
                }
                foreach ($toolResults as $row) {
                    if (! is_array($row) || ! is_string($row['result'] ?? null)) {
                        continue;
                    }
                    foreach (AiChatActivity::extractMarkdownLinks($row['result']) as $link) {
                        $linkBag[] = $link;
                    }
                }
            }
            $links = AiChatActivity::uniqueLinks($linkBag);

            $messages[] = [
                'id' => (string) $record->id,
                'role' => $role,
                'content' => $role === 'assistant' ? AiChatActivity::sanitizeText($content) : $content,
                ...(count($pending) > 0 ? ['pending_approvals' => $pending] : []),
                ...(count($steps) > 0 ? ['steps' => $steps] : []),
                ...(count($links) > 0 ? ['links' => $links] : []),
            ];
        }

        $usage['estimated_cost_usd'] = round((float) $usage['estimated_cost_usd'], 6);

        return [
            'conversation_id' => (string) $conversation->id,
            'title' => filled($conversation->title) ? (string) $conversation->title : 'Chat',
            'messages' => $messages,
            'usage' => $usage,
        ];
    }

    public function deleteConversation(User $user, string $conversationId): void
    {
        $this->assertChatAllowed($user);

        $conversation = $user->conversations()->whereKey($conversationId)->first();

        if ($conversation === null) {
            throw new RuntimeException('Conversation not found.');
        }

        AiConversationMessage::query()->where('conversation_id', $conversation->id)->each(function (AiConversationMessage $message): void {
            $message->delete();
        });
        $conversation->delete();
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  array<string, mixed>|null  $pageContext
     * @return array{
     *     conversation_id: string|null,
     *     text: string,
     *     pending_approvals: list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>,
     *     steps: list<array{type: string, tool: string, label: string, summary: string, status: string, detail: string|null}>,
     *     links: list<array{label: string, href: string}>,
     *     usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int},
     *     model: string|null
     * }
     */
    public function chat(
        User $user,
        string $message,
        ?string $conversationId = null,
        array $uploads = [],
        ?array $pageContext = null,
    ): array {
        $this->assertChatAllowed($user);

        $normalizedContext = is_array($pageContext)
            ? AiChatContext::normalize($pageContext)
            : null;

        try {
            app(AiRiskGuard::class)->assertSafeChatMessage($message);
        } catch (InvalidArgumentException $e) {
            $this->logBlocked($user, $message, $e->getMessage());

            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $attachments = [];
        $attachmentMeta = [];

        if ($uploads !== []) {
            try {
                $stored = app(AiChatAttachmentService::class)->storeForUser($user->id, $uploads);
            } catch (InvalidArgumentException $e) {
                throw new RuntimeException($e->getMessage(), previous: $e);
            }

            foreach ($stored as $row) {
                $attachments[] = $row['attachment'];
                $attachmentMeta[] = [
                    'name' => $row['name'],
                    'mime' => $row['mime'],
                    'size' => $row['size'],
                ];
            }
        }

        if ($conversationId !== null && $conversationId !== '') {
            $this->assertOwnsConversation($user, $conversationId);
        }

        $fencedMessage = app(AiRiskGuard::class)->fenceUserContent('user_message', $message);

        $candidates = AiSdkBridge::candidateModels();
        $started = hrtime(true);
        /** @var list<Throwable> $failures */
        $failures = [];

        foreach ($candidates as $index => $model) {
            $resolved = AiSdkBridge::applyModel($model);
            $agent = KitAgent::make(user: $user, pageContext: $normalizedContext);

            try {
                $this->enrichLangfuseTrace($user, $conversationId);

                $promptable = $conversationId
                    ? $agent->continue($conversationId, as: $user)
                    : $agent->forUser($user);

                $response = $promptable->prompt(
                    $fencedMessage,
                    $attachments,
                    provider: $resolved['provider'],
                    model: $resolved['model'],
                );

                $this->logSuccess($user, $message, $resolved, $started, $response);

                $payload = $this->formatResponse($response);
                if ($attachmentMeta !== []) {
                    $payload['attachments'] = $attachmentMeta;
                }

                app(LangfuseObservability::class)->flush();

                return $payload;
            } catch (Throwable $e) {
                $failures[] = $e;
                $this->logFailure($user, $message, $resolved, $started, $e);
                app(LangfuseObservability::class)->flush();

                if ($index >= count($candidates) - 1) {
                    break;
                }

                Log::info('AI chat primary model failed; trying failover', [
                    'failed_model' => $model['id'],
                    'provider' => $model['provider'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException(
            AiSdkBridge::userMessageFromFailures($failures),
            previous: $failures === [] ? null : $failures[array_key_last($failures)],
        );
    }

    /**
     * @param  array<string, bool|string>  $decisions  Map of tool call id => true/false or 'approve'/'reject'
     * @return array{
     *     conversation_id: string|null,
     *     text: string,
     *     pending_approvals: list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>,
     *     usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int},
     *     model: string|null
     * }
     */
    public function approve(User $user, string $conversationId, array $decisions): array
    {
        $this->assertChatAllowed($user);

        if ($conversationId === '') {
            throw new RuntimeException('conversation_id is required to approve tool calls.');
        }

        $this->assertOwnsConversation($user, $conversationId);

        $normalized = [];
        foreach ($decisions as $id => $value) {
            $normalized[(string) $id] = match (true) {
                $value === true, $value === 'approve', $value === 'approved' => Decision::approve(),
                $value === false, $value === 'reject', $value === 'rejected' => Decision::reject('Rejected by user.'),
                default => Decision::reject('Rejected by user.'),
            };
        }

        $candidates = AiSdkBridge::candidateModels();
        $started = hrtime(true);
        /** @var list<Throwable> $failures */
        $failures = [];

        foreach ($candidates as $index => $model) {
            $resolved = AiSdkBridge::applyModel($model);
            $agent = KitAgent::make(user: $user);

            try {
                $this->enrichLangfuseTrace($user, $conversationId);

                $response = $agent
                    ->continue($conversationId, as: $user)
                    ->prompt(
                        Decisions::from($normalized)->rejectRemaining('Not approved.'),
                        provider: $resolved['provider'],
                        model: $resolved['model'],
                    );

                $this->logSuccess($user, '[tool approval]', $resolved, $started, $response);

                app(LangfuseObservability::class)->flush();

                return $this->formatResponse($response);
            } catch (Throwable $e) {
                $failures[] = $e;
                $this->logFailure($user, '[tool approval]', $resolved, $started, $e);
                app(LangfuseObservability::class)->flush();

                if ($index >= count($candidates) - 1) {
                    break;
                }

                Log::info('AI chat approval primary model failed; trying failover', [
                    'failed_model' => $model['id'],
                    'provider' => $model['provider'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException(
            AiSdkBridge::userMessageFromFailures($failures !== [] ? $failures : [new RuntimeException('AI chat approval failed.')]),
            previous: $failures === [] ? null : $failures[array_key_last($failures)],
        );
    }

    protected function assertChatAllowed(User $user): void
    {
        if (! AiSettings::enabled()) {
            throw new RuntimeException('AI is disabled.');
        }

        if (! AiSettings::chatEnabled()) {
            throw new RuntimeException('General Chat is disabled.');
        }

        if (! $user->can('ai.chat')) {
            throw new RuntimeException('You do not have permission to use General Chat.');
        }

        if (AiSettings::defaultModel() === null || ! AiSettings::defaultModel()['api_key_set']) {
            throw new RuntimeException('No AI model with an API key is configured.');
        }
    }

    protected function assertOwnsConversation(User $user, string $conversationId): void
    {
        $owned = $user->conversations()->whereKey($conversationId)->exists();
        if (! $owned) {
            throw new RuntimeException('Conversation not found.');
        }
    }

    /**
     * @return array{
     *     conversation_id: string|null,
     *     text: string,
     *     pending_approvals: list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>,
     *     steps: list<array{type: string, tool: string, label: string, summary: string, status: string, detail: string|null}>,
     *     links: list<array{label: string, href: string}>,
     *     mutations: list<array{tool: string, resource: string|null, id: int|string|null}>,
     *     usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, estimated_cost_usd: float},
     *     model: string|null
     * }
     */
    protected function formatResponse(AgentResponse $response): array
    {
        $pending = [];
        foreach ($response->pendingApprovals ?? [] as $approval) {
            $pending[] = $approval->toArray();
        }

        $text = AiChatActivity::sanitizeText((string) $response->text);
        $steps = AiChatActivity::stepsFromResponse($response);
        $links = AiChatActivity::linksFromResponse($response);
        $mutations = AiChatActivity::mutationsFromResponse($response);

        $prompt = (int) ($response->usage->promptTokens ?? 0);
        $completion = (int) ($response->usage->completionTokens ?? 0);
        $provider = (string) ($response->meta->provider ?? '');
        $model = (string) ($response->meta->model ?? '');
        if ($provider === '' || $model === '') {
            $default = AiSettings::defaultModel();
            $provider = $provider !== '' ? $provider : (string) ($default['provider'] ?? '');
            $model = $model !== '' ? $model : (string) ($default['model'] ?? '');
        }

        return [
            'conversation_id' => $response->conversationId,
            'text' => $text,
            'pending_approvals' => $pending,
            'steps' => $steps,
            'links' => $links,
            'mutations' => $mutations,
            'usage' => [
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $prompt + $completion,
                'estimated_cost_usd' => AiPricing::estimateUsd($provider, $model, $prompt, $completion),
            ],
            'model' => $response->meta->model ?? null,
        ];
    }

    /**
     * @param  array{provider: string, model: string, model_id?: string}  $resolved
     */
    protected function logSuccess(
        User $user,
        string $message,
        array $resolved,
        int $started,
        AgentResponse $response,
    ): void {
        $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
        $prompt = (int) ($response->usage->promptTokens ?? 0);
        $completion = (int) ($response->usage->completionTokens ?? 0);
        $total = $prompt + $completion;

        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'action' => 'chat',
            'status' => AiUsageLog::STATUS_SUCCESS,
            'provider' => $resolved['provider'],
            'model_id' => $resolved['model_id'] ?? AiSettings::defaultModel()['id'] ?? null,
            'model_name' => $resolved['model'],
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'estimated_cost_usd' => AiPricing::estimateUsd($resolved['provider'], $resolved['model'], $prompt, $completion),
            'duration_ms' => $durationMs,
            'instruction_preview' => mb_substr($message, 0, (int) config('ai.limits.log_preview_chars', 240)),
            'input_preview' => null,
            'output_preview' => mb_substr((string) $response->text, 0, (int) config('ai.limits.log_preview_chars', 240)),
            'logged_at' => now(),
        ]);
    }

    /**
     * @param  array{provider: string, model: string, model_id?: string}  $resolved
     */
    protected function logFailure(
        User $user,
        string $message,
        array $resolved,
        int $started,
        Throwable $e,
    ): void {
        $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);

        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'action' => 'chat',
            'status' => AiUsageLog::STATUS_FAILED,
            'provider' => $resolved['provider'],
            'model_id' => $resolved['model_id'] ?? AiSettings::defaultModel()['id'] ?? null,
            'model_name' => $resolved['model'],
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost_usd' => 0,
            'duration_ms' => $durationMs,
            'instruction_preview' => mb_substr($message, 0, (int) config('ai.limits.log_preview_chars', 240)),
            'error' => mb_substr($e->getMessage(), 0, 500),
            'logged_at' => now(),
        ]);
    }

    protected function logBlocked(User $user, string $message, string $reason): void
    {
        $default = AiSettings::defaultModel();

        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'action' => 'chat_blocked',
            'status' => AiUsageLog::STATUS_FAILED,
            'provider' => $default['provider'] ?? null,
            'model_id' => $default['id'] ?? null,
            'model_name' => $default['model'] ?? null,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost_usd' => 0,
            'duration_ms' => 0,
            'instruction_preview' => mb_substr($message, 0, (int) config('ai.limits.log_preview_chars', 240)),
            'error' => mb_substr($reason, 0, 500),
            'logged_at' => now(),
        ]);
    }

    protected function enrichLangfuseTrace(User $user, ?string $conversationId): void
    {
        app(LangfuseObservability::class)->startChatTrace(
            userId: (string) $user->id,
            conversationId: $conversationId,
        );
    }
}
