import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { AiClientSettings } from '@/composables/useAiAssist';
import { usePermissions } from '@/composables/usePermissions';
import {
    derivePageContext,
    type AiPageContext,
} from '@/lib/aiPageContext';

export type AiChatPendingApproval = {
    id: string;
    tool: string;
    arguments: Record<string, unknown>;
    reason: string | null;
};

export type AiChatStep = {
    type: string;
    tool: string;
    label: string;
    summary: string;
    status: string;
    detail: string | null;
};

export type AiChatLink = {
    label: string;
    href: string;
};

export type AiChatUsage = {
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
    estimated_cost_usd?: number;
};

export type AiChatMutation = {
    tool: string;
    resource: string | null;
    id: number | string | null;
};

export type AiChatMessage = {
    id: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    pendingApprovals?: AiChatPendingApproval[];
    steps?: AiChatStep[];
    links?: AiChatLink[];
    attachments?: Array<{ name: string; mime?: string; size?: number }>;
};

export type AiChatConversationSummary = {
    id: string;
    title: string;
    updated_at: string | null;
    preview: string | null;
};

type ChatResponse = {
    conversation_id: string | null;
    text: string;
    pending_approvals: AiChatPendingApproval[];
    steps?: AiChatStep[];
    links?: AiChatLink[];
    mutations?: AiChatMutation[];
    usage?: AiChatUsage;
    model?: string | null;
    message?: string;
};

type ConversationDetailResponse = {
    conversation_id: string;
    title: string;
    messages: Array<{
        id: string;
        role: string;
        content: string;
        pending_approvals?: AiChatPendingApproval[];
        steps?: AiChatStep[];
        links?: AiChatLink[];
    }>;
    usage?: AiChatUsage;
    message?: string;
};

type JsonError = {
    message?: string;
    pending_approvals?: AiChatPendingApproval[];
    approvals?: AiChatPendingApproval[];
};

const open = ref(false);
const view = ref<'chat' | 'history'>('chat');
const sending = ref(false);
const loadingHistory = ref(false);
const conversationId = ref<string | null>(null);
const messages = ref<AiChatMessage[]>([]);
const conversations = ref<AiChatConversationSummary[]>([]);
const draft = ref('');
const pendingFiles = ref<File[]>([]);
const error = ref<string | null>(null);
const conversationUsage = ref<AiChatUsage>({
    prompt_tokens: 0,
    completion_tokens: 0,
    total_tokens: 0,
    estimated_cost_usd: 0,
});
const historyQuery = ref('');
const historyCursor = ref<string | null>(null);
const historyHasMore = ref(false);
const loadingMoreHistory = ref(false);
const contextDismissedPath = ref<string | null>(null);

const MAX_CHAT_FILES = 5;
const MAX_CHAT_FILE_KB = 10_240;
const CHAT_FILE_ACCEPT =
    '.pdf,.png,.jpg,.jpeg,.webp,.gif,.txt,.csv,application/pdf,image/*,text/plain,text/csv';
const HISTORY_PAGE_SIZE = 12;

let messageSeq = 0;
let refreshTimer: ReturnType<typeof setTimeout> | null = null;
let historySearchTimer: ReturnType<typeof setTimeout> | null = null;
let historyRequestId = 0;
let activeAbort: AbortController | null = null;

function emptyUsage(): AiChatUsage {
    return {
        prompt_tokens: 0,
        completion_tokens: 0,
        total_tokens: 0,
        estimated_cost_usd: 0,
    };
}

function nextId(prefix: string): string {
    messageSeq += 1;

    return `${prefix}-${messageSeq}-${Date.now()}`;
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function isAbortError(error: unknown): boolean {
    return (
        (error instanceof DOMException && error.name === 'AbortError') ||
        (error instanceof Error && error.name === 'AbortError')
    );
}

async function requestJson<T extends { message?: string }>(
    url: string,
    options: RequestInit = {},
): Promise<T> {
    const isFormData =
        typeof FormData !== 'undefined' && options.body instanceof FormData;

    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body && !isFormData
                ? { 'Content-Type': 'application/json' }
                : {}),
            ...(options.headers ?? {}),
        },
        credentials: 'same-origin',
    });

    const data = (await response.json().catch(() => ({}))) as T & JsonError;

    if (!response.ok) {
        const err = new Error(
            data.message || `Request failed (${response.status})`,
        ) as Error & {
            status?: number;
            pendingApprovals?: AiChatPendingApproval[];
        };
        err.status = response.status;
        err.pendingApprovals =
            data.pending_approvals ?? data.approvals ?? undefined;
        throw err;
    }

    return data;
}

function refreshOpenPage(): void {
    if (refreshTimer) {
        clearTimeout(refreshTimer);
    }

    refreshTimer = setTimeout(() => {
        router.reload({
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }, 250);
}

export function useAiChat() {
    const page = usePage();
    const { can } = usePermissions();

    const aiSettings = computed(
        () => page.props.aiSettings as AiClientSettings | undefined,
    );

    const available = computed(
        () =>
            Boolean(aiSettings.value?.enabled) &&
            Boolean(aiSettings.value?.chat_enabled) &&
            can('ai.chat'),
    );

    const livePageContext = computed(() =>
        derivePageContext(String(page.url ?? '/'), String(page.component ?? '')),
    );

    const activeContext = computed<AiPageContext | null>(() => {
        const ctx = livePageContext.value;
        if (!ctx) {
            return null;
        }
        if (contextDismissedPath.value === ctx.path) {
            return null;
        }

        return ctx;
    });

    watch(
        () => livePageContext.value?.path,
        (path, previous) => {
            if (path && path !== previous && contextDismissedPath.value === previous) {
                contextDismissedPath.value = null;
            }
        },
    );

    function dismissContext(): void {
        const path = livePageContext.value?.path;
        contextDismissedPath.value = path ?? null;
    }

    function restoreContext(): void {
        contextDismissedPath.value = null;
    }

    const activeApprovals = computed(() => {
        for (let i = messages.value.length - 1; i >= 0; i -= 1) {
            const message = messages.value[i];
            if (
                message?.role === 'assistant' &&
                message.pendingApprovals?.length
            ) {
                return message.pendingApprovals;
            }
        }

        return [] as AiChatPendingApproval[];
    });

    const approvalMessageId = computed(() => {
        for (let i = messages.value.length - 1; i >= 0; i -= 1) {
            const message = messages.value[i];
            if (
                message?.role === 'assistant' &&
                message.pendingApprovals?.length
            ) {
                return message.id;
            }
        }

        return null;
    });

    function openChat(): void {
        if (!available.value) {
            return;
        }
        open.value = true;
        view.value = 'chat';
        error.value = null;
    }

    function closeChat(): void {
        open.value = false;
    }

    function toggleChat(): void {
        if (open.value) {
            closeChat();
        } else {
            openChat();
        }
    }

    function showHistory(): void {
        view.value = 'history';
        void loadConversations({ reset: true });
    }

    function showChat(): void {
        view.value = 'chat';
    }

    function resetConversation(): void {
        conversationId.value = null;
        messages.value = [];
        draft.value = '';
        pendingFiles.value = [];
        error.value = null;
        conversationUsage.value = emptyUsage();
        view.value = 'chat';
    }

    function setHistoryQuery(value: string): void {
        historyQuery.value = value;
        if (historySearchTimer) {
            clearTimeout(historySearchTimer);
        }
        historySearchTimer = setTimeout(() => {
            if (view.value === 'history') {
                void loadConversations({ reset: true });
            }
        }, 250);
    }

    async function loadConversations(options: {
        reset?: boolean;
        append?: boolean;
    } = {}): Promise<void> {
        if (!available.value) {
            return;
        }

        const reset = options.reset ?? !options.append;
        const append = Boolean(options.append);

        if (append) {
            if (!historyHasMore.value || loadingMoreHistory.value || loadingHistory.value) {
                return;
            }
            loadingMoreHistory.value = true;
        } else {
            loadingHistory.value = true;
            if (reset) {
                historyCursor.value = null;
                historyHasMore.value = false;
            }
        }

        error.value = null;
        const requestId = ++historyRequestId;

        try {
            const params = new URLSearchParams();
            params.set('limit', String(HISTORY_PAGE_SIZE));
            const q = historyQuery.value.trim();
            if (q) {
                params.set('q', q);
            }
            if (append && historyCursor.value) {
                params.set('cursor', historyCursor.value);
            }

            const data = await requestJson<{
                conversations: AiChatConversationSummary[];
                next_cursor?: string | null;
                has_more?: boolean;
            }>(`/ai/chat/conversations?${params.toString()}`);

            if (requestId !== historyRequestId) {
                return;
            }

            const page = data.conversations ?? [];
            conversations.value = append
                ? [...conversations.value, ...page]
                : page;
            historyCursor.value = data.next_cursor ?? null;
            historyHasMore.value = Boolean(data.has_more);
        } catch (e) {
            if (requestId !== historyRequestId) {
                return;
            }
            error.value =
                e instanceof Error
                    ? e.message
                    : 'Could not load chat history.';
        } finally {
            if (requestId === historyRequestId) {
                loadingHistory.value = false;
                loadingMoreHistory.value = false;
            }
        }
    }

    async function loadMoreConversations(): Promise<void> {
        await loadConversations({ append: true });
    }

    function addFiles(fileList: FileList | File[] | null): void {
        if (!fileList) {
            return;
        }

        const incoming = Array.from(fileList);
        const next = [...pendingFiles.value];

        for (const file of incoming) {
            if (next.length >= MAX_CHAT_FILES) {
                error.value = `You can attach at most ${MAX_CHAT_FILES} files.`;
                break;
            }
            if (file.size > MAX_CHAT_FILE_KB * 1024) {
                error.value = `${file.name} is too large (max ${MAX_CHAT_FILE_KB} KB).`;
                continue;
            }
            next.push(file);
        }

        pendingFiles.value = next;
    }

    function removePendingFile(index: number): void {
        pendingFiles.value = pendingFiles.value.filter((_, i) => i !== index);
    }

    function clearPendingFiles(): void {
        pendingFiles.value = [];
    }

    function stopAgent(): void {
        if (!sending.value || !activeAbort) {
            return;
        }

        activeAbort.abort();
        activeAbort = null;
    }

    function beginRequest(): AbortController {
        activeAbort?.abort();
        const controller = new AbortController();
        activeAbort = controller;

        return controller;
    }

    function endRequest(controller: AbortController): void {
        if (activeAbort === controller) {
            activeAbort = null;
        }
    }

    async function openConversation(id: string): Promise<void> {
        if (!available.value || sending.value) {
            return;
        }

        loadingHistory.value = true;
        error.value = null;

        try {
            const data = await requestJson<ConversationDetailResponse>(
                `/ai/chat/conversations/${encodeURIComponent(id)}`,
            );

            conversationId.value = data.conversation_id;
            messages.value = (data.messages ?? [])
                .filter(
                    (message) =>
                        message.role === 'user' || message.role === 'assistant',
                )
                .map((message) => ({
                    id: message.id,
                    role: message.role as 'user' | 'assistant',
                    content: message.content || '',
                    pendingApprovals: message.pending_approvals?.length
                        ? message.pending_approvals
                        : undefined,
                    steps: message.steps?.length ? message.steps : undefined,
                    links: message.links?.length ? message.links : undefined,
                }));
            conversationUsage.value = data.usage?.total_tokens
                ? {
                      prompt_tokens: data.usage.prompt_tokens ?? 0,
                      completion_tokens: data.usage.completion_tokens ?? 0,
                      total_tokens: data.usage.total_tokens ?? 0,
                      estimated_cost_usd: data.usage.estimated_cost_usd ?? 0,
                  }
                : emptyUsage();
            draft.value = '';
            view.value = 'chat';
        } catch (e) {
            error.value =
                e instanceof Error
                    ? e.message
                    : 'Could not open that conversation.';
        } finally {
            loadingHistory.value = false;
        }
    }

    async function deleteConversation(id: string): Promise<void> {
        if (!available.value) {
            return;
        }

        try {
            await requestJson<{ ok: boolean }>(
                `/ai/chat/conversations/${encodeURIComponent(id)}`,
                { method: 'DELETE' },
            );

            conversations.value = conversations.value.filter(
                (item) => item.id !== id,
            );

            if (conversationId.value === id) {
                resetConversation();
                view.value = 'history';
            }
        } catch (e) {
            error.value =
                e instanceof Error
                    ? e.message
                    : 'Could not delete that conversation.';
        }
    }

    function addUsage(usage?: AiChatUsage): void {
        if (!usage) {
            return;
        }

        const prompt = Number(usage.prompt_tokens) || 0;
        const completion = Number(usage.completion_tokens) || 0;
        const total =
            Number(usage.total_tokens) || prompt + completion;
        const cost = Number(usage.estimated_cost_usd) || 0;

        if (total <= 0 && cost <= 0) {
            return;
        }

        conversationUsage.value = {
            prompt_tokens: conversationUsage.value.prompt_tokens + prompt,
            completion_tokens:
                conversationUsage.value.completion_tokens + completion,
            total_tokens: conversationUsage.value.total_tokens + total,
            estimated_cost_usd:
                (conversationUsage.value.estimated_cost_usd ?? 0) + cost,
        };
    }

    function appendAssistant(data: ChatResponse): void {
        if (data.conversation_id) {
            conversationId.value = data.conversation_id;
        }

        messages.value.push({
            id: nextId('assistant'),
            role: 'assistant',
            content: data.text || '',
            pendingApprovals:
                data.pending_approvals?.length > 0
                    ? data.pending_approvals
                    : undefined,
            steps: data.steps?.length ? data.steps : undefined,
            links: data.links?.length ? data.links : undefined,
        });

        addUsage(data.usage);

        if (data.mutations?.length) {
            refreshOpenPage();
        }
    }

    function restoreApprovals(
        approvals: AiChatPendingApproval[] | undefined,
    ): void {
        if (!approvals?.length) {
            return;
        }

        const last = [...messages.value]
            .reverse()
            .find((m) => m.role === 'assistant');
        if (last) {
            last.pendingApprovals = approvals;
        }
    }

    async function sendMessage(text?: string): Promise<void> {
        const content = (text ?? draft.value).trim();
        const files = [...pendingFiles.value];
        if ((!content && files.length === 0) || sending.value || !available.value) {
            return;
        }

        error.value = null;
        draft.value = '';
        pendingFiles.value = [];
        view.value = 'chat';

        const displayContent =
            content ||
            (files.length === 1
                ? `Attached: ${files[0]?.name ?? 'file'}`
                : `Attached ${files.length} files`);

        messages.value.push({
            id: nextId('user'),
            role: 'user',
            content: displayContent,
            attachments: files.map((file) => ({
                name: file.name,
                mime: file.type,
                size: file.size,
            })),
        });
        sending.value = true;
        const controller = beginRequest();

        try {
            const form = new FormData();
            form.append('message', content);
            if (conversationId.value) {
                form.append('conversation_id', conversationId.value);
            }
            const ctx = activeContext.value;
            if (ctx) {
                form.append('context[label]', ctx.label);
                form.append('context[path]', ctx.path);
                if (ctx.resource) {
                    form.append('context[resource]', ctx.resource);
                }
                if (ctx.record_id) {
                    form.append('context[record_id]', String(ctx.record_id));
                }
            }
            for (const file of files) {
                form.append('files[]', file);
            }

            const data = await requestJson<ChatResponse>('/ai/chat', {
                method: 'POST',
                body: form,
                signal: controller.signal,
                headers: {
                    // Let the browser set multipart boundary; do not force JSON.
                },
            });
            appendAssistant(data);
        } catch (e) {
            if (isAbortError(e)) {
                messages.value.push({
                    id: nextId('system'),
                    role: 'system',
                    content: 'Stopped.',
                });
            } else {
                error.value =
                    e instanceof Error
                        ? e.message
                        : 'AI chat failed unexpectedly.';
                messages.value.push({
                    id: nextId('system'),
                    role: 'system',
                    content: error.value,
                });
            }
        } finally {
            endRequest(controller);
            sending.value = false;
        }
    }

    async function decideApprovals(
        decisions: Record<string, boolean>,
    ): Promise<void> {
        if (!conversationId.value || sending.value) {
            return;
        }

        const host = [...messages.value]
            .reverse()
            .find((m) => m.role === 'assistant' && m.pendingApprovals?.length);
        const snapshot = host?.pendingApprovals
            ? [...host.pendingApprovals]
            : undefined;

        error.value = null;
        sending.value = true;
        const controller = beginRequest();

        try {
            const data = await requestJson<ChatResponse>('/ai/chat/approve', {
                method: 'POST',
                body: JSON.stringify({
                    conversation_id: conversationId.value,
                    decisions,
                }),
                signal: controller.signal,
            });

            if (host) {
                host.pendingApprovals = undefined;
            }

            appendAssistant(data);
        } catch (e) {
            if (isAbortError(e)) {
                restoreApprovals(snapshot);
                messages.value.push({
                    id: nextId('system'),
                    role: 'system',
                    content: 'Stopped.',
                });
            } else {
                const pending =
                    e &&
                    typeof e === 'object' &&
                    'pendingApprovals' in e &&
                    Array.isArray(
                        (e as { pendingApprovals?: unknown }).pendingApprovals,
                    )
                        ? ((e as { pendingApprovals: AiChatPendingApproval[] })
                              .pendingApprovals)
                        : snapshot;

                restoreApprovals(pending);

                error.value =
                    e instanceof Error
                        ? e.message
                        : 'AI chat approval failed unexpectedly.';
                messages.value.push({
                    id: nextId('system'),
                    role: 'system',
                    content: error.value,
                });
            }
        } finally {
            endRequest(controller);
            sending.value = false;
        }
    }

    async function approveAll(
        approvals: AiChatPendingApproval[],
    ): Promise<void> {
        const decisions: Record<string, boolean> = {};
        for (const approval of approvals) {
            decisions[approval.id] = true;
        }
        await decideApprovals(decisions);
    }

    async function rejectAll(
        approvals: AiChatPendingApproval[],
    ): Promise<void> {
        const decisions: Record<string, boolean> = {};
        for (const approval of approvals) {
            decisions[approval.id] = false;
        }
        await decideApprovals(decisions);
    }

    return {
        open,
        view,
        sending,
        loadingHistory,
        loadingMoreHistory,
        conversationId,
        messages,
        conversations,
        draft,
        pendingFiles,
        error,
        available,
        activeApprovals,
        approvalMessageId,
        conversationUsage,
        historyQuery,
        historyHasMore,
        activeContext,
        livePageContext,
        openChat,
        closeChat,
        toggleChat,
        showHistory,
        showChat,
        resetConversation,
        setHistoryQuery,
        loadConversations,
        loadMoreConversations,
        openConversation,
        deleteConversation,
        addFiles,
        removePendingFile,
        clearPendingFiles,
        sendMessage,
        stopAgent,
        dismissContext,
        restoreContext,
        approveAll,
        rejectAll,
        MAX_CHAT_FILES,
        CHAT_FILE_ACCEPT,
    };
}
