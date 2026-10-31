<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BookOpen,
    Brain,
    ChartColumn,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Code2,
    History,
    Lightbulb,
    Loader2,
    Plus,
    Search,
    Send,
    Sparkles,
    Trash2,
    Zap,
    Activity,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import AiDotIcon from '@/components/AiDotIcon.vue';
import {
    MisChartCard,
    MisDataTable,
    MisStatCard,
    MisStatGrid,
} from '@/components/mis';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslations } from '@/composables/useTranslations';
import { dustOut } from '@/lib/dustOut';

type Insight = { title: string; body: string; tone: 'info' | 'positive' | 'warning' };
type Kpi = { id: string; label: string; value: string; hint: string | null };
type ChartSection = {
    id: string;
    title: string;
    chart_type: 'bar' | 'line' | 'pie';
    labels: string[];
    datasets: Array<{ label: string; data: number[] }>;
};
type TableSection = {
    id: string;
    title: string;
    columns: string[];
    rows: Array<Record<string, unknown>>;
    truncated?: boolean;
    total_rows?: number;
};
type QueryMeta = {
    id: string;
    title: string;
    question: string;
    sql: string;
    row_count: number;
};
type Report = {
    title: string;
    summary: string;
    prompt: string;
    kpis: Kpi[];
    charts: ChartSection[];
    tables: TableSection[];
    insights: Insight[];
    queries: QueryMeta[];
    partial?: boolean;
    usage?: {
        prompt_tokens: number;
        completion_tokens: number;
        total_tokens: number;
        estimated_cost_usd?: number;
    } | null;
    model?: string | null;
};

type Example = { label: string; prompt: string };

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    kind: 'text' | 'report';
    report?: Report | null;
};

type StoredConversation = {
    id: string;
    title: string;
    updatedAt: number;
    messages: ChatMessage[];
};

type ProgressStep = {
    id: string;
    label: string;
    status: 'running' | 'done' | 'error' | 'pending';
    detail?: string | null;
};

type StageId = 'understand' | 'write' | 'search' | 'insights';

const STORAGE_KEY = 'kit-ai-analytics-chats-v2';
const LEGACY_STORAGE_KEY = 'kit-ai-analytics-chat-v1';

const STAGE_DEFS: Array<{
    id: StageId;
    label: string;
    icon: Component;
}> = [
    { id: 'understand', label: 'Understanding', icon: Brain },
    { id: 'write', label: 'Writing SQL', icon: Code2 },
    { id: 'search', label: 'Searching', icon: Search },
    { id: 'insights', label: 'Insights', icon: Sparkles },
];

const props = defineProps<{
    canGenerate: boolean;
    analyticsEnabled: boolean;
    aiEnabled: boolean;
    setupUrl: string;
    examples: Example[];
    usage?: {
        today_requests: number;
        today_tokens: number;
        today_cost_usd: number;
        month_requests: number;
        month_tokens: number;
        month_cost_usd: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AI Analytics', href: '/ai/analytics' }],
    },
});

const page = usePage();
const { t } = useTranslations();

const prompt = ref('');
const mode = ref<'efficient' | 'detailed'>('efficient');
const loading = ref(false);
const error = ref<string | null>(null);
const messages = ref<ChatMessage[]>([]);
const conversations = ref<StoredConversation[]>([]);
const activeConversationId = ref<string | null>(null);
const historyOpen = ref(false);
const historyExpanded = ref(true);
const threadEl = ref<HTMLElement | null>(null);
const promptFocused = ref(false);
const steps = ref<ProgressStep[]>([]);
const statusDetail = ref<string | null>(null);
const sessionTokens = ref(0);
const sessionCost = ref(0);
const sessionReports = ref(0);
const threadThumbVisible = ref(false);
const threadThumbTop = ref(12);
const THREAD_THUMB_H = 22;
const THREAD_THUMB_PAD = 10;

const HISTORY_UI_KEY = 'kit-ai-analytics-history-ui';

const sheetSide = computed<'left' | 'right'>(() =>
    page.props.dir === 'rtl' ? 'left' : 'right',
);
const isRtl = computed(() => page.props.dir === 'rtl');

const usageTodayTokens = computed(
    () => (props.usage?.today_tokens ?? 0) + sessionTokens.value,
);
const usageTodayRequests = computed(
    () => (props.usage?.today_requests ?? 0) + sessionReports.value,
);
const usageTodayCost = computed(
    () => (props.usage?.today_cost_usd ?? 0) + sessionCost.value,
);
const usageMonthTokens = computed(
    () => (props.usage?.month_tokens ?? 0) + sessionTokens.value,
);
const usageMonthRequests = computed(
    () => (props.usage?.month_requests ?? 0) + sessionReports.value,
);
const usageMonthCost = computed(
    () => (props.usage?.month_cost_usd ?? 0) + sessionCost.value,
);
const chatTokens = computed(() =>
    messages.value.reduce((sum, message) => {
        if (message.kind === 'report' && message.report?.usage?.total_tokens) {
            return sum + message.report.usage.total_tokens;
        }
        return sum;
    }, 0),
);
const chatCost = computed(() =>
    messages.value.reduce((sum, message) => {
        if (message.kind === 'report' && message.report?.usage?.estimated_cost_usd) {
            return sum + Number(message.report.usage.estimated_cost_usd);
        }
        return sum;
    }, 0),
);

const hasChatUsage = computed(
    () => chatTokens.value > 0 || chatCost.value > 0,
);

function formatTokens(value: number): string {
    return value.toLocaleString();
}

function formatUsd(value: number): string {
    if (!Number.isFinite(value) || value <= 0) {
        return '$0.00';
    }
    if (value < 0.01) {
        return `$${value.toFixed(4)}`;
    }

    return `$${value.toFixed(2)}`;
}

function toggleHistory(): void {
    historyExpanded.value = !historyExpanded.value;
    try {
        localStorage.setItem(
            HISTORY_UI_KEY,
            historyExpanded.value ? '1' : '0',
        );
    } catch {
        // ignore
    }
}

function openHistoryMobile(): void {
    historyOpen.value = true;
}

const setupNeeded = computed(() => !props.aiEnabled || !props.analyticsEnabled);
const isEmpty = computed(() => messages.value.length === 0 && !loading.value);
const sortedConversations = computed(() => {
    const byId = new Map<string, StoredConversation>();

    for (const conversation of conversations.value) {
        byId.set(conversation.id, conversation);
    }

    if (activeConversationId.value && messages.value.length > 0) {
        const current = byId.get(activeConversationId.value);
        byId.set(activeConversationId.value, {
            id: activeConversationId.value,
            title: titleFromMessages(messages.value),
            updatedAt: current?.updatedAt ?? Date.now(),
            messages: messages.value,
        });
    }

    return [...byId.values()]
        .filter((c) => c.messages.length > 0)
        .sort((a, b) => b.updatedAt - a.updatedAt);
});
const activeStage = computed<StageId>(() => {
    const running = [...steps.value].reverse().find((s) => s.status === 'running');
    const id = running?.id ?? steps.value.at(-1)?.id ?? 'planning';

    if (id === 'insights' || id === 'complete') {
        return 'insights';
    }
    if (id === 'write') {
        return 'write';
    }
    if (id === 'fallback' || id.startsWith('query') || id === 'search') {
        return 'search';
    }
    if (id === 'planning' && running?.status === 'running') {
        return 'understand';
    }
    if (id === 'planning') {
        return 'write';
    }

    return 'search';
});

const activeStageIndex = computed(() =>
    Math.max(
        0,
        STAGE_DEFS.findIndex((s) => s.id === activeStage.value),
    ),
);

const progressFill = computed(() => {
    const i = activeStageIndex.value;
    const n = STAGE_DEFS.length - 1;
    const base = n <= 0 ? 100 : (i / n) * 100;

    return Math.min(96, Math.max(8, base + 4));
});

const activeHeadline = computed(() => {
    const running = [...steps.value].reverse().find((s) => s.status === 'running');
    if (running?.label) {
        return running.label;
    }

    return (
        {
            understand: t('Understanding your question…'),
            write: t('Writing safe queries…'),
            search: t('Searching your data…'),
            insights: t('Writing insights…'),
        }[activeStage.value] ?? t('Working…')
    );
});

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function newId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function historyPayload(): Array<{ role: 'user' | 'assistant'; content: string }> {
    return messages.value.slice(-12).map((m) => {
        if (m.kind === 'report' && m.report) {
            const kpiBits = m.report.kpis
                .slice(0, 6)
                .map((k) => `${k.label}=${k.value}`)
                .join(', ');
            const chartBits = m.report.charts
                .slice(0, 4)
                .map((c) => `${c.chart_type}:${c.title}`)
                .join(', ');
            const tableBits = m.report.tables
                .slice(0, 3)
                .map((t) => t.title)
                .join(', ');
            const insightBits = m.report.insights
                .slice(0, 2)
                .map((i) => i.title)
                .join(', ');
            const parts = [
                `[report] ${m.report.title}.`,
                m.report.summary,
                kpiBits ? `KPIs: ${kpiBits}.` : '',
                chartBits ? `Charts: ${chartBits}.` : '',
                tableBits ? `Tables: ${tableBits}.` : '',
                insightBits ? `Insights: ${insightBits}.` : '',
                'Surface: read-only analytics.',
            ].filter(Boolean);

            return { role: m.role, content: parts.join(' ').slice(0, 1200) };
        }

        return { role: m.role, content: m.content.slice(0, 1200) };
    });
}

function titleFromMessages(list: ChatMessage[]): string {
    const firstUser = list.find((m) => m.role === 'user' && m.content.trim());
    if (!firstUser) {
        return t('New chat');
    }
    const text = firstUser.content.trim().replace(/\s+/g, ' ');
    return text.length > 42 ? `${text.slice(0, 42)}…` : text;
}

function lightReport(report: Report | null | undefined): Report | null {
    if (!report) {
        return null;
    }

    return {
        ...report,
        tables: (report.tables ?? []).map((table) => ({
            ...table,
            rows: table.rows.slice(0, 40),
        })),
        charts: (report.charts ?? []).map((chart) => ({
            ...chart,
            labels: chart.labels.slice(0, 40),
            datasets: chart.datasets.map((dataset) => ({
                ...dataset,
                data: dataset.data.slice(0, 40),
            })),
        })),
        queries: (report.queries ?? []).slice(0, 8),
    };
}

function serializeConversations(
    list: StoredConversation[],
    compact: boolean,
): StoredConversation[] {
    return list.map((conversation) => ({
        id: conversation.id,
        title: conversation.title,
        updatedAt: conversation.updatedAt,
        messages: conversation.messages.map((message) => ({
            id: message.id,
            role: message.role,
            content: message.content,
            kind: message.kind,
            report:
                message.kind === 'report'
                    ? compact
                        ? lightReport(message.report)
                        : (message.report ?? null)
                    : null,
        })),
    }));
}

function persistStore(): void {
    const payloadBase = {
        activeId: activeConversationId.value,
    };

    const attempts = [
        serializeConversations(conversations.value, false),
        serializeConversations(conversations.value, true),
        serializeConversations(
            conversations.value
                .filter((c) => c.messages.length > 0)
                .slice(0, 12)
                .map((c) => ({
                    ...c,
                    messages: c.messages.map((m) =>
                        m.kind === 'report' && m.report
                            ? {
                                  ...m,
                                  report: {
                                      ...m.report,
                                      tables: [],
                                      charts: [],
                                      queries: [],
                                  },
                              }
                            : m,
                    ),
                })),
            true,
        ),
    ];

    for (const conversationsPayload of attempts) {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({
                    ...payloadBase,
                    conversations: conversationsPayload,
                }),
            );
            return;
        } catch {
            // try a smaller payload
        }
    }
}

function syncActiveConversation(): void {
    if (!activeConversationId.value) {
        return;
    }
    const index = conversations.value.findIndex(
        (c) => c.id === activeConversationId.value,
    );
    const next: StoredConversation = {
        id: activeConversationId.value,
        title: titleFromMessages(messages.value),
        updatedAt: Date.now(),
        messages: messages.value.map((m) => ({ ...m })),
    };

    if (index === -1) {
        conversations.value = [next, ...conversations.value];
    } else {
        const copy = conversations.value.slice();
        copy[index] = next;
        conversations.value = copy;
    }
    persistStore();
}

function ensureActiveConversation(): void {
    if (activeConversationId.value) {
        const exists = conversations.value.some(
            (c) => c.id === activeConversationId.value,
        );
        if (exists) {
            return;
        }
    }
    const id = activeConversationId.value || newId();
    conversations.value = [
        {
            id,
            title: t('New chat'),
            updatedAt: Date.now(),
            messages: [],
        },
        ...conversations.value.filter((c) => c.id !== id),
    ];
    activeConversationId.value = id;
    persistStore();
}

function persistMessages(): void {
    ensureActiveConversation();
    syncActiveConversation();
}

function loadStore(): void {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw) as {
                activeId?: string | null;
                conversations?: StoredConversation[];
            };
            if (Array.isArray(parsed.conversations)) {
                conversations.value = parsed.conversations.filter(
                    (c) =>
                        c &&
                        typeof c.id === 'string' &&
                        Array.isArray(c.messages),
                );
            }
            activeConversationId.value =
                typeof parsed.activeId === 'string' ? parsed.activeId : null;
            const active = conversations.value.find(
                (c) => c.id === activeConversationId.value,
            );
            if (active) {
                messages.value = active.messages.map((m) => ({ ...m }));
            } else if (conversations.value[0]) {
                activeConversationId.value = conversations.value[0].id;
                messages.value = conversations.value[0].messages.map((m) => ({
                    ...m,
                }));
            }
            return;
        }

        const legacy = sessionStorage.getItem(LEGACY_STORAGE_KEY);
        if (!legacy) {
            return;
        }
        const parsed = JSON.parse(legacy) as ChatMessage[];
        if (!Array.isArray(parsed) || parsed.length === 0) {
            return;
        }
        const id = newId();
        const list = parsed.filter(
            (m) => m && (m.role === 'user' || m.role === 'assistant'),
        );
        conversations.value = [
            {
                id,
                title: titleFromMessages(list),
                updatedAt: Date.now(),
                messages: list,
            },
        ];
        activeConversationId.value = id;
        messages.value = list;
        persistStore();
        sessionStorage.removeItem(LEGACY_STORAGE_KEY);
    } catch {
        // keep in-memory state if storage is unreadable
    }
}

function openConversation(id: string): void {
    if (loading.value) {
        return;
    }
    const found = conversations.value.find((c) => c.id === id);
    if (!found) {
        return;
    }
    activeConversationId.value = id;
    messages.value = found.messages.map((m) => ({ ...m }));
    error.value = null;
    steps.value = [];
    statusDetail.value = null;
    historyOpen.value = false;
    persistStore();
    void scrollToBottom();
}

function clearChat(): void {
    if (loading.value) {
        return;
    }
    conversations.value = conversations.value.filter(
        (c) => c.messages.length > 0,
    );
    if (messages.value.length === 0 && activeConversationId.value) {
        persistStore();
        return;
    }
    const id = newId();
    conversations.value.unshift({
        id,
        title: t('New chat'),
        updatedAt: Date.now(),
        messages: [],
    });
    activeConversationId.value = id;
    messages.value = [];
    error.value = null;
    steps.value = [];
    statusDetail.value = null;
    persistStore();
}

function deleteConversation(id: string): void {
    if (loading.value) {
        return;
    }
    conversations.value = conversations.value.filter((c) => c.id !== id);
    if (activeConversationId.value === id) {
        const next =
            conversations.value.find((c) => c.messages.length > 0) ?? null;
        activeConversationId.value = next?.id ?? null;
        messages.value = next ? next.messages.map((m) => ({ ...m })) : [];
    }
    persistStore();
}

const pendingDeleteId = ref<string | null>(null);
const deletingChat = ref(false);
const historyRowEls = new Map<string, HTMLElement>();

const pendingDeleteTitle = computed(() => {
    if (!pendingDeleteId.value) {
        return '';
    }
    return (
        sortedConversations.value.find((c) => c.id === pendingDeleteId.value)
            ?.title ?? t('this chat')
    );
});

function bindHistoryRow(id: string, el: unknown): void {
    if (el instanceof HTMLElement) {
        historyRowEls.set(id, el);
        return;
    }
    historyRowEls.delete(id);
}

function requestDelete(id: string): void {
    if (loading.value || deletingChat.value) {
        return;
    }
    pendingDeleteId.value = id;
}

async function confirmPendingDelete(): Promise<void> {
    const id = pendingDeleteId.value;
    if (!id || deletingChat.value) {
        return;
    }

    deletingChat.value = true;
    pendingDeleteId.value = null;
    await nextTick();

    const row =
        historyRowEls.get(id) ?? historyRowEls.get(`m-${id}`) ?? null;
    if (row) {
        await dustOut(row, { collapse: true, preset: 'row' });
    }

    deleteConversation(id);
    deletingChat.value = false;
}

function useExample(example: Example): void {
    prompt.value = example.prompt;
    error.value = null;
}

function upsertStep(step: ProgressStep): void {
    const index = steps.value.findIndex((s) => s.id === step.id);
    if (index === -1) {
        steps.value.push(step);
    } else {
        steps.value[index] = { ...steps.value[index], ...step };
    }
    if (step.detail) {
        statusDetail.value = step.detail;
    }
    if (step.status === 'running' && step.label) {
        statusDetail.value = step.detail ?? step.label;
    }
}

async function scrollToBottom(): Promise<void> {
    await nextTick();
    threadEl.value?.scrollTo({
        top: threadEl.value.scrollHeight,
        behavior: 'smooth',
    });
    updateThreadThumb();
}

function updateThreadThumb(): void {
    const el = threadEl.value;
    if (!el) {
        threadThumbVisible.value = false;
        return;
    }

    const maxScroll = el.scrollHeight - el.clientHeight;
    if (maxScroll <= 8) {
        threadThumbVisible.value = false;
        return;
    }

    const track = Math.max(0, el.clientHeight - THREAD_THUMB_H - THREAD_THUMB_PAD * 2);
    threadThumbTop.value =
        THREAD_THUMB_PAD + (el.scrollTop / maxScroll) * track;
    threadThumbVisible.value = true;
}

function onThreadScroll(): void {
    updateThreadThumb();
}

async function send(): Promise<void> {
    const text = prompt.value.trim();
    if (!text) {
        error.value = t('Type a question to generate analytics.');
        return;
    }
    if (setupNeeded.value || !props.canGenerate) {
        error.value = t(
            'Enable AI analytics under Settings → AI → Tasks, then try again.',
        );
        return;
    }
    if (loading.value) {
        return;
    }

    const history = historyPayload();
    messages.value.push({
        id: newId(),
        role: 'user',
        content: text,
        kind: 'text',
    });
    prompt.value = '';
    persistMessages();

    loading.value = true;
    error.value = null;
    statusDetail.value = null;
    steps.value = [
        {
            id: 'planning',
            label: t('Understanding your question…'),
            status: 'running',
        },
    ];
    await scrollToBottom();

    try {
        const response = await fetch('/ai/analytics/generate?stream=1', {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                prompt: text,
                mode: mode.value,
                history,
            }),
        });

        if (!response.ok) {
            const data = (await response.json().catch(() => ({}))) as {
                message?: string;
            };
            throw new Error(
                data.message || `Request failed (${response.status})`,
            );
        }

        if (!response.body) {
            throw new Error(t('Could not generate analytics. Try again.'));
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let streamError: string | null = null;
        let gotResult = false;

        while (true) {
            const { done, value } = await reader.read();
            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });
            const chunks = buffer.split('\n\n');
            buffer = chunks.pop() ?? '';

            for (const chunk of chunks) {
                const lines = chunk.split('\n');
                let eventName = 'message';
                const dataLines: string[] = [];

                for (const line of lines) {
                    if (line.startsWith('event:')) {
                        eventName = line.slice(6).trim();
                    } else if (line.startsWith('data:')) {
                        dataLines.push(line.slice(5).trim());
                    }
                }

                if (dataLines.length === 0) {
                    continue;
                }

                const payload = JSON.parse(dataLines.join('\n')) as Record<
                    string,
                    unknown
                >;

                if (eventName === 'step') {
                    upsertStep({
                        id: String(payload.id ?? 'step'),
                        label: String(payload.label ?? ''),
                        status:
                            (payload.status as ProgressStep['status']) ??
                            'running',
                        detail:
                            typeof payload.detail === 'string'
                                ? payload.detail
                                : null,
                    });
                    await scrollToBottom();
                } else if (eventName === 'done') {
                    if (payload.kind === 'chat') {
                        messages.value.push({
                            id: newId(),
                            role: 'assistant',
                            content:
                                typeof payload.message === 'string'
                                    ? payload.message
                                    : t('Hello! How can I help?'),
                            kind: 'text',
                        });
                        gotResult = true;
                    } else {
                        const report = (payload.report as Report) ?? null;
                        if (report) {
                            messages.value.push({
                                id: newId(),
                                role: 'assistant',
                                content: report.summary || report.title,
                                kind: 'report',
                                report,
                            });
                            const tokens = report.usage?.total_tokens ?? 0;
                            if (tokens > 0) {
                                sessionTokens.value += tokens;
                            }
                            const cost = Number(report.usage?.estimated_cost_usd ?? 0);
                            if (cost > 0) {
                                sessionCost.value += cost;
                            }
                            sessionReports.value += 1;
                            gotResult = true;
                        }
                    }
                } else if (eventName === 'error') {
                    streamError =
                        typeof payload.message === 'string'
                            ? payload.message
                            : t('Could not generate analytics. Try again.');
                }
            }
        }

        if (streamError) {
            messages.value.push({
                id: newId(),
                role: 'assistant',
                content: streamError,
                kind: 'text',
            });
            persistMessages();
            throw new Error(streamError);
        }

        if (!gotResult) {
            const fallbackMsg = t('Could not generate analytics. Try again.');
            messages.value.push({
                id: newId(),
                role: 'assistant',
                content: fallbackMsg,
                kind: 'text',
            });
            persistMessages();
            throw new Error(fallbackMsg);
        }

        persistMessages();
        await scrollToBottom();
    } catch (e) {
        error.value =
            e instanceof Error
                ? e.message
                : t('Could not generate analytics. Try again.');
        // Prefer the assistant bubble; keep banner only if no assistant reply was added.
        const last = messages.value[messages.value.length - 1];
        if (
            last?.role === 'assistant' &&
            last.kind === 'text' &&
            last.content === error.value
        ) {
            error.value = null;
        }
        await scrollToBottom();
    } finally {
        loading.value = false;
        steps.value = [];
        statusDetail.value = null;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        void send();
    }
}

function insightTone(tone: string): string {
    if (tone === 'positive') {
        return 'border-[var(--brand-accent)]/15 bg-[var(--brand-accent)]/[0.04]';
    }
    if (tone === 'warning') {
        return 'border-amber-500/20 bg-amber-500/[0.04]';
    }

    return 'border-border/50 bg-muted/30';
}

function kpiTone(
    index: number,
): 'default' | 'info' | 'success' | 'warning' {
    const tones = ['default', 'info', 'success', 'warning'] as const;

    return tones[index % tones.length];
}

function cellValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }
    if (typeof value === 'number') {
        return value.toLocaleString();
    }

    return String(value);
}

onMounted(() => {
    try {
        const stored = localStorage.getItem(HISTORY_UI_KEY);
        if (stored === '0') {
            historyExpanded.value = false;
        } else if (stored === '1') {
            historyExpanded.value = true;
        }
    } catch {
        // keep default expanded
    }
    loadStore();
    if (messages.value.length > 0) {
        persistMessages();
    }
    void scrollToBottom();
    window.addEventListener('resize', updateThreadThumb);
});

watch(
    () => messages.value.length,
    () => {
        if (messages.value.length > 0) {
            persistMessages();
        }
        void scrollToBottom();
    },
);

watch(loading, () => {
    void nextTick().then(updateThreadThumb);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateThreadThumb);
});
</script>

<template>
    <Head :title="t('AI Analytics')" />

    <div
        class="ai-analytics flex min-h-0 flex-1 overflow-hidden border-t border-border/40 bg-background"
    >
        <!-- Main chat -->
        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header
                class="flex shrink-0 items-center justify-end gap-1 border-b border-border/40 px-4 py-2.5 sm:px-6 md:hidden"
            >
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    :title="t('History')"
                    :aria-label="t('History')"
                    :disabled="loading"
                    @click="openHistoryMobile"
                >
                    <History class="size-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-[12px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    :title="t('New chat')"
                    :aria-label="t('New chat')"
                    :disabled="loading"
                    @click="clearChat"
                >
                    <Plus class="size-3.5" />
                    <span>{{ t('New chat') }}</span>
                </button>
            </header>

            <div
                v-if="setupNeeded"
                class="mx-4 mt-3 flex shrink-0 flex-col gap-3 rounded-xl border border-[var(--school-navy)]/12 bg-[var(--school-navy)]/[0.03] px-4 py-3 sm:mx-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-foreground/90">
                    <template v-if="!aiEnabled">
                        {{ t('Turn AI on, then enable analytics under Tasks.') }}
                    </template>
                    <template v-else>
                        {{
                            t(
                                'Analytics is off. Enable “Allow analytics” under Settings → AI → Tasks.',
                            )
                        }}
                    </template>
                </p>
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="shrink-0 rounded-lg"
                >
                    <Link :href="setupUrl" class="gap-1.5">
                        {{ t('Open AI settings') }}
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </Button>
            </div>

            <!-- Thread -->
            <div class="relative min-h-0 flex-1">
                <div
                    ref="threadEl"
                    class="ai-analytics-scroll absolute inset-0 overflow-y-auto px-4 py-5 sm:px-6"
                    @scroll="onThreadScroll"
                >
                <div class="mx-auto flex h-full w-full max-w-3xl flex-col">
                    <div
                        v-if="isEmpty"
                        class="ai-analytics-empty relative flex flex-1 flex-col items-center justify-center px-2 pb-6 text-center"
                    >
                        <div class="ai-analytics-empty-glow" aria-hidden="true" />
                        <div class="ai-analytics-empty-orb relative mb-6">
                            <span class="ai-analytics-agent-ring" aria-hidden="true" />
                            <span
                                class="ai-analytics-agent-ring ai-analytics-agent-ring--lag"
                                aria-hidden="true"
                            />
                            <AiDotIcon size="lg" playing variant="lime" />
                        </div>
                        <p class="ai-analytics-fade text-xl font-semibold tracking-tight sm:text-2xl">
                            {{ t('What should we analyze?') }}
                        </p>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            {{
                                t(
                                    'Read-only reports from data you can access — users, logins, field reports, trends. I can’t delete or change records here.',
                                )
                            }}
                        </p>
                        <div
                            v-if="examples.length"
                            class="mt-8 grid w-full max-w-xl grid-cols-1 gap-2 sm:grid-cols-2"
                        >
                            <button
                                v-for="(example, index) in examples"
                                :key="example.label"
                                type="button"
                                class="ai-analytics-example group flex items-center gap-3 rounded-2xl border border-border/60 bg-card/80 px-4 py-3 text-start transition hover:border-[var(--school-navy)]/25 hover:bg-[var(--school-navy)]/[0.03] hover:shadow-sm"
                                :style="{ animationDelay: `${index * 60}ms` }"
                                :disabled="loading || setupNeeded"
                                @click="useExample(example)"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-foreground/8 text-foreground transition group-hover:bg-foreground group-hover:text-background"
                                >
                                    <ChartColumn class="size-4" :stroke-width="1.75" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-semibold text-foreground">
                                        {{ t(example.label) }}
                                    </span>
                                    <span class="mt-0.5 block truncate text-[11px] text-muted-foreground">
                                        {{ example.prompt }}
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-else class="space-y-5 py-1">
                    <template v-for="message in messages" :key="message.id">
                        <div
                            v-if="message.role === 'user'"
                            class="ai-analytics-msg flex justify-end"
                        >
                            <div
                                class="max-w-[85%] rounded-2xl rounded-br-md bg-foreground px-4 py-2.5 text-[14px] leading-relaxed text-background"
                            >
                                {{ message.content }}
                            </div>
                        </div>

                        <div
                            v-else-if="message.kind === 'text'"
                            class="ai-analytics-msg flex gap-3"
                        >
                            <AiDotIcon size="md" class="mt-0.5" variant="lime" />
                            <p
                                class="min-w-0 max-w-[90%] pt-0.5 text-[14px] leading-relaxed text-foreground"
                            >
                                {{ message.content }}
                            </p>
                        </div>

                        <div
                            v-else-if="message.report"
                            class="ai-analytics-msg flex gap-3"
                        >
                            <AiDotIcon size="md" class="mt-0.5" variant="lime" />
                            <div
                                class="min-w-0 flex-1 space-y-5 rounded-2xl border border-border/50 bg-card px-4 py-4 sm:px-5"
                            >
                                <div class="space-y-1">
                                    <h2 class="text-base font-semibold tracking-tight">
                                        {{ message.report.title }}
                                    </h2>
                                    <p
                                        v-if="message.report.partial"
                                        class="rounded-lg border border-amber-500/20 bg-amber-500/[0.06] px-3 py-2 text-sm leading-relaxed text-amber-900 dark:text-amber-100"
                                    >
                                        {{
                                            t(
                                                'Some parts of this report could not be answered from data you can access. Showing what succeeded.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="message.report.summary"
                                        class="text-sm leading-relaxed text-muted-foreground"
                                    >
                                        {{ message.report.summary }}
                                    </p>
                                </div>

                                <MisStatGrid
                                    v-if="message.report.kpis.length"
                                    :columns="
                                        message.report.kpis.length >= 4
                                            ? 4
                                            : message.report.kpis.length === 2
                                              ? 2
                                              : 3
                                    "
                                >
                                    <MisStatCard
                                        v-for="(kpi, index) in message.report
                                            .kpis"
                                        :key="kpi.id"
                                        :label="kpi.label"
                                        :value="kpi.value"
                                        :hint="kpi.hint"
                                        :icon="ChartColumn"
                                        :tone="kpiTone(index)"
                                    />
                                </MisStatGrid>

                                <div
                                    v-if="message.report.insights.length"
                                    class="grid gap-2 md:grid-cols-2"
                                >
                                    <article
                                        v-for="insight in message.report.insights"
                                        :key="insight.title + insight.body"
                                        class="rounded-xl border px-3.5 py-3"
                                        :class="insightTone(insight.tone)"
                                    >
                                        <h4
                                            class="flex items-center gap-1.5 text-sm font-semibold"
                                        >
                                            <Lightbulb class="size-3.5 opacity-70" />
                                            {{ insight.title }}
                                        </h4>
                                        <p
                                            class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                        >
                                            {{ insight.body }}
                                        </p>
                                    </article>
                                </div>

                                <div
                                    v-if="message.report.charts.length"
                                    class="grid gap-3 lg:grid-cols-2"
                                >
                                    <MisChartCard
                                        v-for="chart in message.report.charts"
                                        :key="chart.id"
                                        :title="chart.title"
                                        :type="chart.chart_type"
                                        :labels="chart.labels"
                                        :datasets="chart.datasets"
                                    />
                                </div>

                                <div
                                    v-if="message.report.tables.length"
                                    class="space-y-4"
                                >
                                    <div
                                        v-for="table in message.report.tables"
                                        :key="table.id"
                                        class="space-y-2"
                                    >
                                        <h4 class="text-sm font-semibold">
                                            {{ table.title }}
                                        </h4>
                                        <MisDataTable
                                            :columns="
                                                table.columns.map((col) => ({
                                                    key: col,
                                                    label: col,
                                                    class: 'tabular-nums',
                                                }))
                                            "
                                            :rows="
                                                table.rows.map((row, index) => ({
                                                    id: `${table.id}-${index}`,
                                                    ...Object.fromEntries(
                                                        table.columns.map((col) => [
                                                            col,
                                                            cellValue(row[col]),
                                                        ]),
                                                    ),
                                                }))
                                            "
                                            :empty-label="t('No rows')"
                                        />
                                    </div>
                                </div>

                                <Collapsible
                                    v-if="message.report.queries.length"
                                    class="rounded-xl border border-border/50"
                                >
                                    <CollapsibleTrigger
                                        class="group flex w-full items-center justify-between px-3.5 py-2.5 text-start text-xs font-medium text-muted-foreground transition hover:text-foreground"
                                    >
                                        {{ t('Queries used') }}
                                        <ChevronDown
                                            class="size-3.5 transition group-data-[state=open]:rotate-180"
                                        />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent
                                        class="space-y-3 border-t border-border/50 px-3.5 py-3"
                                    >
                                        <div
                                            v-for="query in message.report.queries"
                                            :key="query.id"
                                            class="space-y-1.5"
                                        >
                                            <p class="text-xs font-medium">
                                                {{ query.title }}
                                            </p>
                                            <pre
                                                class="overflow-x-auto rounded-lg bg-muted/40 px-3 py-2 font-mono text-[10px] leading-relaxed text-foreground/70"
                                            ><code>{{ query.sql }}</code></pre>
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            </div>
                        </div>
                    </template>

                    <div
                        v-if="loading"
                        class="ai-analytics-progress flex gap-3"
                        aria-busy="true"
                        aria-live="polite"
                    >
                        <span class="ai-analytics-agent relative mt-0.5 shrink-0">
                            <span class="ai-analytics-agent-ring" aria-hidden="true" />
                            <span class="ai-analytics-agent-ring ai-analytics-agent-ring--lag" aria-hidden="true" />
                            <AiDotIcon size="md" playing variant="lime" />
                        </span>

                        <div class="ai-analytics-agent-card min-w-0 flex-1 overflow-hidden rounded-2xl border border-border/50 bg-card">
                            <div class="relative px-4 pt-3.5 pb-3">
                                <div class="ai-analytics-agent-glow" aria-hidden="true" />
                                <div class="relative flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                            {{ t('Agent') }}
                                        </p>
                                        <p
                                            :key="activeHeadline"
                                            class="ai-analytics-fade mt-0.5 truncate text-sm font-semibold"
                                        >
                                            {{ activeHeadline }}
                                        </p>
                                    </div>
                                    <span
                                        class="ai-analytics-dots shrink-0 text-[var(--school-navy)]"
                                        aria-hidden="true"
                                    >
                                        <i /><i /><i />
                                    </span>
                                </div>

                                <div class="relative mt-3.5 h-1 overflow-hidden rounded-full bg-border/60">
                                    <div
                                        class="ai-analytics-fill relative h-full rounded-full bg-foreground transition-[width] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                        :style="{ width: `${progressFill}%` }"
                                    >
                                        <span class="ai-analytics-sheen absolute inset-y-0 w-1/2" />
                                    </div>
                                </div>

                                <ol class="relative mt-4 grid grid-cols-4 gap-2">
                                    <li
                                        v-for="(stage, index) in STAGE_DEFS"
                                        :key="stage.id"
                                        class="ai-analytics-stage flex flex-col items-center gap-1.5"
                                        :class="{
                                            'ai-analytics-stage--done': index < activeStageIndex,
                                            'ai-analytics-stage--active': index === activeStageIndex,
                                            'ai-analytics-stage--pending': index > activeStageIndex,
                                        }"
                                    >
                                        <span class="ai-analytics-stage-node relative flex size-8 items-center justify-center rounded-full border">
                                            <Check
                                                v-if="index < activeStageIndex"
                                                class="size-3.5"
                                                :stroke-width="2.5"
                                            />
                                            <component
                                                :is="stage.icon"
                                                v-else
                                                class="size-3.5"
                                                :stroke-width="2"
                                            />
                                        </span>
                                        <span class="text-center text-[10px] font-medium leading-tight">
                                            {{ t(stage.label) }}
                                        </span>
                                    </li>
                                </ol>

                                <div
                                    v-if="statusDetail"
                                    :key="statusDetail"
                                    class="ai-analytics-fade mt-3 flex items-center gap-2 rounded-xl bg-muted/40 px-3 py-2"
                                >
                                    <Loader2
                                        class="size-3.5 shrink-0 animate-spin text-[var(--school-navy)]"
                                    />
                                    <p class="truncate text-[12px] text-muted-foreground">
                                        {{ statusDetail }}
                                    </p>
                                </div>
                                <div v-else class="ai-analytics-skeleton mt-3 space-y-2" aria-hidden="true">
                                    <span class="ai-analytics-skeleton-line w-[88%]" />
                                    <span class="ai-analytics-skeleton-line w-[64%]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="error"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                    </div>
                </div>
                </div>
                <div
                    v-show="threadThumbVisible"
                    class="ai-analytics-thumb pointer-events-none absolute end-1.5 z-10 w-1.5 rounded-full bg-muted-foreground/40 transition-[top] duration-75"
                    :style="{
                        top: `${threadThumbTop}px`,
                        height: `${THREAD_THUMB_H}px`,
                    }"
                    aria-hidden="true"
                />
            </div>

            <!-- Composer -->
            <div class="shrink-0 border-t border-border/40 px-4 py-3 sm:px-6">
                <section
                    class="mx-auto max-w-3xl overflow-hidden rounded-2xl border bg-card transition-[border-color,box-shadow] duration-200"
                    :class="
                        promptFocused
                            ? 'border-[var(--school-navy)]/30 shadow-[0_0_0_3px_color-mix(in_oklab,var(--school-navy)_8%,transparent)]'
                            : 'border-border/70'
                    "
                >
                    <label class="sr-only" for="ai-analytics-prompt">{{
                        t('Analytics prompt')
                    }}</label>
                    <textarea
                        id="ai-analytics-prompt"
                        v-model="prompt"
                        rows="2"
                        :disabled="loading"
                        class="max-h-36 min-h-[3rem] w-full resize-none bg-transparent px-4 pt-3.5 pb-1 text-[14px] leading-relaxed outline-none placeholder:text-muted-foreground/45 disabled:opacity-70"
                        :placeholder="t('Ask a follow-up or a new question…')"
                        @keydown="onKeydown"
                        @focus="promptFocused = true"
                        @blur="promptFocused = false"
                    />
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 px-3 pb-3"
                    >
                        <div
                            class="flex items-center rounded-lg bg-muted/50 p-0.5"
                        >
                            <button
                                type="button"
                                class="inline-flex h-7 items-center gap-1 rounded-md px-2.5 text-[11px] font-medium transition"
                                :class="
                                    mode === 'efficient'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :disabled="loading"
                                @click="mode = 'efficient'"
                            >
                                <Zap class="size-3" />
                                {{ t('Efficient') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-7 items-center gap-1 rounded-md px-2.5 text-[11px] font-medium transition"
                                :class="
                                    mode === 'detailed'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :disabled="loading"
                                @click="mode = 'detailed'"
                            >
                                <BookOpen class="size-3" />
                                {{ t('Detailed') }}
                            </button>
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-9 items-center justify-center rounded-xl bg-foreground text-background transition hover:bg-foreground/90 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="loading || setupNeeded || !prompt.trim()"
                            :aria-label="loading ? t('Working…') : t('Send')"
                            @click="send"
                        >
                            <Loader2 v-if="loading" class="size-4 animate-spin" />
                            <Send v-else class="size-3.5" />
                        </button>
                    </div>
                </section>

                <div
                    v-if="hasChatUsage"
                    class="mx-auto mt-2 flex max-w-3xl items-center justify-between gap-3 px-1 text-[11px] text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <Activity class="size-3 opacity-70" />
                        {{ t('This chat') }}
                    </span>
                    <span class="tabular-nums text-foreground/75">
                        {{ formatTokens(chatTokens) }}
                        {{ t('tokens') }}
                        <span class="mx-1 text-border">·</span>
                        {{ formatUsd(chatCost) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Desktop history sidebar -->
        <aside
            class="ai-analytics-history relative z-10 flex min-h-0 shrink-0 flex-col border-s border-border/50 bg-[#fafafa] transition-[width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] max-md:hidden dark:border-white/10 dark:bg-card/40"
            :class="historyExpanded ? 'w-72 xl:w-80' : 'w-11'"
        >
            <!-- Slim rail -->
            <div
                v-if="!historyExpanded"
                class="flex h-full flex-col items-center gap-2 px-1 py-3"
            >
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-lg bg-foreground text-background shadow-sm transition hover:bg-foreground/90"
                    :title="t('History')"
                    :aria-label="t('Expand history')"
                    @click="toggleHistory"
                >
                    <History class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-white hover:text-foreground dark:hover:bg-muted"
                    :title="t('New chat')"
                    :aria-label="t('New chat')"
                    :disabled="loading"
                    @click="clearChat"
                >
                    <Plus class="size-3.5" />
                </button>
                <div class="my-1 h-px w-4 bg-border/80" />
                <div class="ai-analytics-scroll flex min-h-0 w-full flex-1 flex-col items-center gap-2.5 overflow-y-auto py-1">
                    <button
                        v-for="item in sortedConversations.slice(0, 12)"
                        :key="item.id"
                        type="button"
                        class="inline-flex size-6 shrink-0 items-center justify-center rounded-full transition"
                        :title="item.title"
                        :aria-label="item.title"
                        @click="openConversation(item.id)"
                    >
                        <span
                            class="block size-2 rounded-full transition"
                            :class="
                                item.id === activeConversationId
                                    ? 'scale-125 bg-[var(--school-navy)]'
                                    : 'bg-muted-foreground/35 hover:bg-muted-foreground/70'
                            "
                        />
                    </button>
                </div>
                <button
                    type="button"
                    class="mt-auto inline-flex size-7 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-white hover:text-foreground dark:hover:bg-muted"
                    :title="t('Expand history')"
                    :aria-label="t('Expand history')"
                    @click="toggleHistory"
                >
                    <ChevronLeft v-if="!isRtl" class="size-3.5" />
                    <ChevronRight v-else class="size-3.5" />
                </button>
            </div>

            <!-- Expanded panel -->
            <template v-else>
                <div class="flex shrink-0 flex-col gap-2.5 border-b border-border/40 px-2.5 py-3">
                    <div class="flex items-center justify-between gap-2 px-0.5">
                        <h2 class="text-[12px] font-semibold tracking-tight">
                            {{ t('History') }}
                        </h2>
                        <button
                            type="button"
                            class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-white hover:text-foreground dark:hover:bg-muted"
                            :title="t('Collapse')"
                            :aria-label="t('Collapse')"
                            @click="toggleHistory"
                        >
                            <ChevronRight v-if="!isRtl" class="size-3.5" />
                            <ChevronLeft v-else class="size-3.5" />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-border/70 bg-white text-[11px] font-medium text-foreground transition hover:border-[var(--school-navy)]/25 hover:bg-[var(--school-navy)]/[0.03] dark:border-white/10 dark:bg-muted"
                        :disabled="loading"
                        @click="clearChat"
                    >
                        <Plus class="size-3.5" />
                        {{ t('New chat') }}
                    </button>
                </div>

                <div class="ai-analytics-scroll min-h-0 flex-1 overflow-y-auto px-1.5 py-1.5">
                    <div
                        v-if="sortedConversations.length === 0"
                        class="flex h-full min-h-[10rem] flex-col items-center justify-center gap-1.5 px-3 text-center"
                    >
                        <p class="text-[11px] text-muted-foreground">
                            {{ t('No chats yet') }}
                        </p>
                    </div>

                    <div v-else class="space-y-0.5">
                        <div
                            v-for="item in sortedConversations"
                            :key="item.id"
                            :ref="(el) => bindHistoryRow(item.id, el)"
                            data-dust-row
                            class="group relative overflow-hidden rounded-lg transition"
                            :class="
                                item.id === activeConversationId
                                    ? 'bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] ring-1 ring-border/50 dark:bg-muted dark:ring-white/10'
                                    : 'hover:bg-white/80 dark:hover:bg-muted/70'
                            "
                        >
                            <span
                                v-if="item.id === activeConversationId"
                                class="absolute inset-y-1.5 start-1 w-0.5 rounded-full bg-[var(--school-navy)]"
                            />
                            <button
                                type="button"
                                class="w-full truncate px-2.5 py-2 pe-8 text-start text-[12px] font-medium text-foreground"
                                @click="openConversation(item.id)"
                            >
                                {{ item.title }}
                            </button>
                            <button
                                type="button"
                                class="absolute end-1 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground opacity-0 transition hover:bg-destructive/10 hover:text-destructive group-hover:opacity-100 focus-visible:opacity-100"
                                :aria-label="t('Delete')"
                                :disabled="deletingChat"
                                @click.stop="requestDelete(item.id)"
                            >
                                <Trash2 class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 border-t border-border/40 px-2.5 py-3">
                    <p class="mb-2 px-0.5 text-[11px] font-medium text-muted-foreground">
                        {{ t('Usage') }}
                    </p>
                    <div class="space-y-2 rounded-xl border border-border/50 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-muted">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">{{ t('Today') }}</span>
                            <span class="text-[12px] font-semibold tabular-nums text-foreground">
                                {{ formatUsd(usageTodayCost) }}
                            </span>
                        </div>
                        <p class="text-[11px] tabular-nums text-muted-foreground">
                            {{ formatTokens(usageTodayTokens) }}
                            {{ t('tokens') }}
                            ·
                            {{ usageTodayRequests }}
                            {{ t('reports') }}
                        </p>
                        <div class="h-px bg-border/60" />
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">{{ t('Month') }}</span>
                            <span class="text-[12px] font-semibold tabular-nums text-foreground">
                                {{ formatUsd(usageMonthCost) }}
                            </span>
                        </div>
                        <p class="text-[11px] tabular-nums text-muted-foreground">
                            {{ formatTokens(usageMonthTokens) }}
                            {{ t('tokens') }}
                            ·
                            {{ usageMonthRequests }}
                            {{ t('reports') }}
                        </p>
                    </div>
                </div>
            </template>
        </aside>

        <!-- Mobile history sheet -->
        <Sheet :open="historyOpen" @update:open="historyOpen = $event">
            <SheetContent
                :side="sheetSide"
                class="flex w-full max-w-[20rem] flex-col gap-0 bg-[#fafafa] p-0 sm:max-w-[20rem] dark:bg-card"
            >
                <SheetHeader class="space-y-3 border-b border-border/40 px-4 py-3.5 pe-12 text-start">
                    <div>
                        <SheetTitle class="text-[14px] font-semibold">
                            {{ t('History') }}
                        </SheetTitle>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            <template v-if="sortedConversations.length">
                                {{ sortedConversations.length }}
                                {{ t('chats') }}
                            </template>
                            <template v-else>
                                {{ t('No chats yet') }}
                            </template>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-border/70 bg-white text-[12px] font-medium dark:border-white/10 dark:bg-muted"
                        :disabled="loading"
                        @click="
                            clearChat();
                            historyOpen = false;
                        "
                    >
                        <Plus class="size-3.5" />
                        {{ t('New chat') }}
                    </button>
                </SheetHeader>

                <div class="ai-analytics-scroll min-h-0 flex-1 overflow-y-auto px-2 py-2">
                    <div
                        v-if="sortedConversations.length === 0"
                        class="flex h-full min-h-[12rem] flex-col items-center justify-center gap-2 px-4 text-center"
                    >
                        <History class="size-5 text-muted-foreground/50" />
                        <p class="text-[12px] text-muted-foreground">
                            {{ t('No chats yet') }}
                        </p>
                    </div>

                    <div v-else class="space-y-0.5">
                        <div
                            v-for="item in sortedConversations"
                            :key="`m-${item.id}`"
                            :ref="(el) => bindHistoryRow(`m-${item.id}`, el)"
                            data-dust-row
                            class="group relative overflow-hidden rounded-lg transition"
                            :class="
                                item.id === activeConversationId
                                    ? 'bg-white shadow-sm ring-1 ring-border/50 dark:bg-muted dark:ring-white/10'
                                    : 'hover:bg-white/80 dark:hover:bg-muted/70'
                            "
                        >
                            <button
                                type="button"
                                class="w-full truncate px-2.5 py-2 pe-8 text-start text-[12px] font-medium text-foreground"
                                @click="openConversation(item.id)"
                            >
                                {{ item.title }}
                            </button>
                            <button
                                type="button"
                                class="absolute end-1 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground opacity-0 transition hover:bg-destructive/10 hover:text-destructive group-hover:opacity-100"
                                :aria-label="t('Delete')"
                                :disabled="deletingChat"
                                @click.stop="requestDelete(item.id)"
                            >
                                <Trash2 class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 border-t border-border/40 px-3 py-3">
                    <p class="mb-2 text-[11px] font-medium text-muted-foreground">
                        {{ t('Usage') }}
                    </p>
                    <div class="space-y-2 rounded-xl border border-border/50 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-muted">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">{{ t('Today') }}</span>
                            <span class="text-[12px] font-semibold tabular-nums">
                                {{ formatUsd(usageTodayCost) }}
                            </span>
                        </div>
                        <p class="text-[11px] tabular-nums text-muted-foreground">
                            {{ formatTokens(usageTodayTokens) }}
                            {{ t('tokens') }}
                            ·
                            {{ usageTodayRequests }}
                            {{ t('reports') }}
                        </p>
                        <div class="h-px bg-border/60" />
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">{{ t('Month') }}</span>
                            <span class="text-[12px] font-semibold tabular-nums">
                                {{ formatUsd(usageMonthCost) }}
                            </span>
                        </div>
                        <p class="text-[11px] tabular-nums text-muted-foreground">
                            {{ formatTokens(usageMonthTokens) }}
                            {{ t('tokens') }}
                            ·
                            {{ usageMonthRequests }}
                            {{ t('reports') }}
                        </p>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <Dialog
            :open="pendingDeleteId !== null"
            @update:open="(open) => !open && (pendingDeleteId = null)"
        >
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ t('Delete chat?') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'This will permanently remove “:title” from history.',
                                { title: pendingDeleteTitle },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="deletingChat"
                        @click="pendingDeleteId = null"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deletingChat"
                        @click="confirmPendingDelete"
                    >
                        {{ t('Delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.ai-analytics-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.ai-analytics-scroll::-webkit-scrollbar {
    width: 0;
    height: 0;
    display: none;
}

.ai-analytics-thumb {
    opacity: 0.85;
}

.ai-analytics-empty {
    animation: ai-analytics-msg-in 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.ai-analytics-empty-glow {
    pointer-events: none;
    position: absolute;
    top: 28%;
    left: 50%;
    width: min(28rem, 90%);
    height: 14rem;
    transform: translate(-50%, -50%);
    background: radial-gradient(
        closest-side,
        color-mix(in oklab, var(--school-navy) 10%, transparent),
        transparent 70%
    );
    filter: blur(8px);
}

.ai-analytics-empty-orb {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.ai-analytics-example {
    animation: ai-analytics-msg-in 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.ai-analytics-agent {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.ai-analytics-agent-ring {
    position: absolute;
    inset: -4px;
    border-radius: 9999px;
    border: 1.5px solid color-mix(in oklab, var(--school-navy) 35%, transparent);
    animation: ai-analytics-ring 1.8s cubic-bezier(0.22, 1, 0.36, 1) infinite;
}

.ai-analytics-agent-ring--lag {
    animation-delay: 0.55s;
}

.ai-analytics-agent-card {
    position: relative;
    box-shadow: 0 10px 30px -22px color-mix(in oklab, var(--school-navy) 45%, transparent);
    animation: ai-analytics-card-in 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}

.ai-analytics-agent-glow {
    pointer-events: none;
    position: absolute;
    inset: 0;
    background:
        radial-gradient(
            120% 80% at 0% 0%,
            color-mix(in oklab, var(--school-navy) 8%, transparent),
            transparent 55%
        );
    opacity: 0.9;
}

.ai-analytics-fill {
    box-shadow: 0 0 14px color-mix(in oklab, var(--school-navy) 40%, transparent);
}

.ai-analytics-sheen {
    background: linear-gradient(
        90deg,
        transparent,
        color-mix(in oklab, white 65%, transparent),
        transparent
    );
    animation: ai-analytics-sheen 1.4s ease-in-out infinite;
}

.ai-analytics-stage-node {
    background: var(--card);
    border-color: var(--border);
    color: color-mix(in oklab, var(--muted-foreground) 55%, transparent);
    transition:
        border-color 0.45s ease,
        background-color 0.45s ease,
        color 0.45s ease,
        box-shadow 0.45s ease,
        transform 0.45s ease;
}

.ai-analytics-stage--done .ai-analytics-stage-node {
    border-color: var(--foreground);
    background: var(--foreground);
    color: var(--background);
}

.ai-analytics-stage--active .ai-analytics-stage-node {
    border-color: var(--foreground);
    color: var(--foreground);
    box-shadow: 0 0 0 4px color-mix(in oklab, var(--foreground) 12%, transparent);
    animation: ai-analytics-node-pulse 1.4s ease-in-out infinite;
}

.ai-analytics-stage--done,
.ai-analytics-stage--active {
    color: var(--foreground);
}

.ai-analytics-stage--pending {
    color: color-mix(in oklab, var(--muted-foreground) 55%, transparent);
}

.ai-analytics-fade {
    animation: ai-analytics-fade 0.35s ease-out;
}

.ai-analytics-msg {
    animation: ai-analytics-msg-in 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}

.ai-analytics-progress {
    animation: ai-analytics-msg-in 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

.ai-analytics-skeleton-line {
    display: block;
    height: 8px;
    border-radius: 9999px;
    background: linear-gradient(
        90deg,
        color-mix(in oklab, var(--muted) 70%, transparent) 0%,
        color-mix(in oklab, var(--muted-foreground) 12%, transparent) 45%,
        color-mix(in oklab, var(--muted) 70%, transparent) 100%
    );
    background-size: 200% 100%;
    animation: ai-analytics-shimmer 1.35s ease-in-out infinite;
}

.ai-analytics-dots {
    display: inline-flex;
    gap: 4px;
    align-items: center;
}

.ai-analytics-dots i {
    display: block;
    width: 4px;
    height: 4px;
    border-radius: 9999px;
    background: currentColor;
    opacity: 0.35;
    animation: ai-analytics-dot 1.1s ease-in-out infinite;
}

.ai-analytics-dots i:nth-child(2) {
    animation-delay: 0.15s;
}

.ai-analytics-dots i:nth-child(3) {
    animation-delay: 0.3s;
}

@keyframes ai-analytics-sheen {
    0% {
        transform: translateX(-120%);
    }
    100% {
        transform: translateX(220%);
    }
}

@keyframes ai-analytics-ring {
    0% {
        transform: scale(0.85);
        opacity: 0.7;
    }
    100% {
        transform: scale(1.55);
        opacity: 0;
    }
}

@keyframes ai-analytics-node-pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.06);
    }
}

@keyframes ai-analytics-card-in {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes ai-analytics-msg-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes ai-analytics-fade {
    from {
        opacity: 0.25;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes ai-analytics-shimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}

@keyframes ai-analytics-dot {
    0%,
    80%,
    100% {
        transform: translateY(0);
        opacity: 0.3;
    }
    40% {
        transform: translateY(-3px);
        opacity: 1;
    }
}
</style>
