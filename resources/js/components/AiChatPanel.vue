<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    AtSign,
    BarChart3,
    CheckCircle2,
    CircleAlert,
    FileText,
    History,
    List,
    LoaderCircle,
    MessageSquarePlus,
    Paperclip,
    Plus,
    Search,
    Send,
    Sparkles,
    Square,
    Trash2,
    X,
    XCircle,
    Zap,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import AiChatMessageContent from '@/components/AiChatMessageContent.vue';
import AiDotIcon from '@/components/AiDotIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    useAiChat,
    type AiChatPendingApproval,
} from '@/composables/useAiChat';
import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const { t } = useTranslations();
const {
    open,
    view,
    sending,
    loadingHistory,
    loadingMoreHistory,
    messages,
    conversations,
    draft,
    pendingFiles,
    available,
    activeApprovals,
    historyQuery,
    historyHasMore,
    closeChat,
    showHistory,
    showChat,
    resetConversation,
    setHistoryQuery,
    loadMoreConversations,
    openConversation,
    deleteConversation,
    addFiles,
    removePendingFile,
    sendMessage,
    stopAgent,
    dismissContext,
    restoreContext,
    approveAll,
    rejectAll,
    conversationId,
    conversationUsage,
    activeContext,
    livePageContext,
    CHAT_FILE_ACCEPT,
} = useAiChat();

const listEl = ref<HTMLElement | null>(null);
const historyListEl = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLTextAreaElement | null>(null);
const fileInputEl = ref<HTMLInputElement | null>(null);
const historySearch = ref('');
const deletingId = ref<string | null>(null);

const sheetSide = computed<'left' | 'right'>(() =>
    page.props.dir === 'rtl' ? 'left' : 'right',
);

const userName = computed(() => {
    const full = String(page.props.auth?.user?.name ?? '').trim();
    if (!full) {
        return '';
    }

    return full.split(/\s+/)[0] ?? full;
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    const name = userName.value;

    let key = 'Good evening';
    if (hour < 12) {
        key = 'Good morning';
    } else if (hour < 17) {
        key = 'Good afternoon';
    }

    return name ? t(`${key}, :name`, { name }) : t(key);
});

const quickActions = computed(() => [
    {
        id: 'list-resources',
        label: t('What can you do?'),
        prompt: t(
            'List the resources you can work on and what I am allowed to create, update, or delete',
        ),
        icon: List,
        tone: 'text-foreground',
    },
    {
        id: 'analytics',
        label: t('Ask a report'),
        prompt: t('How many field reports do I have, grouped by status?'),
        icon: BarChart3,
        tone: 'text-foreground',
    },
    {
        id: 'fast',
        label: t('Quick help'),
        prompt: t('Give me a short overview of what I should do next today.'),
        icon: Zap,
        tone: 'text-foreground',
    },
    {
        id: 'magic',
        label: t('Suggest tasks'),
        prompt: t('Suggest three useful tasks I can run with you right now.'),
        icon: Sparkles,
        tone: 'text-foreground',
    },
]);

const headerTitle = computed(() =>
    view.value === 'history' ? t('Chat history') : t('General Chat'),
);

watch(
    () => [messages.value.length, sending.value, view.value, activeApprovals.value.length] as const,
    async () => {
        await nextTick();
        if (view.value === 'chat' && listEl.value) {
            listEl.value.scrollTop = listEl.value.scrollHeight;
        }
    },
);

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        if (view.value === 'chat') {
            inputEl.value?.focus();
        }
    }
});

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void sendMessage();
    }
}

function openFilePicker(): void {
    fileInputEl.value?.click();
}

function onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    addFiles(input?.files ?? null);
    if (input) {
        input.value = '';
    }
}

function canSend(): boolean {
    return Boolean(draft.value.trim() || pendingFiles.value.length > 0);
}

function toolFriendlyName(tool: string): string {
    const map: Record<string, string> = {
        DeleteRecord: t('Delete record'),
        CreateRecord: t('Create record'),
        UpdateRecord: t('Update record'),
        ListRecords: t('List records'),
        GetRecord: t('Get record'),
        ListResources: t('List sections'),
        ListPermissions: t('List permissions'),
        RunAnalyticsQuery: t('Analytics'),
    };

    return map[tool] ?? tool;
}

function approvalHeadline(approval: AiChatPendingApproval): string {
    const resource = String(approval.arguments.resource ?? 'record');
    const id = approval.arguments.id;

    if (approval.tool === 'DeleteRecord') {
        return id != null
            ? t('Delete :resource #:id?', {
                  resource,
                  id: String(id),
              })
            : t('Delete this record?');
    }

    return approval.reason || toolFriendlyName(approval.tool);
}

function stepStatusIcon(status: string) {
    if (status === 'done') {
        return CheckCircle2;
    }
    if (status === 'failed' || status === 'denied') {
        return XCircle;
    }
    if (status === 'awaiting_approval') {
        return CircleAlert;
    }

    return LoaderCircle;
}

function formatWhen(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const diffMs = Date.now() - date.getTime();
    const minute = 60_000;
    const hour = 60 * minute;
    const day = 24 * hour;

    if (diffMs < minute) {
        return t('Just now');
    }
    if (diffMs < hour) {
        return t(':count m ago', {
            count: String(Math.max(1, Math.floor(diffMs / minute))),
        });
    }
    if (diffMs < day) {
        return t(':count h ago', {
            count: String(Math.max(1, Math.floor(diffMs / hour))),
        });
    }
    if (diffMs < 7 * day) {
        return t(':count d ago', {
            count: String(Math.max(1, Math.floor(diffMs / day))),
        });
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function formatUsageTokens(value: number): string {
    return value.toLocaleString();
}

function formatUsageCost(value: number | undefined): string {
    if (!Number.isFinite(value) || !value || value <= 0) {
        return '';
    }
    if (value < 0.01) {
        return `$${value.toFixed(4)}`;
    }

    return `$${value.toFixed(2)}`;
}

function usageLabel(usage: {
    total_tokens: number;
    estimated_cost_usd?: number;
}): string {
    const tokens = formatUsageTokens(usage.total_tokens);
    const cost = formatUsageCost(usage.estimated_cost_usd);

    return cost
        ? t(':tokens tokens · :cost', { tokens, cost })
        : t(':tokens tokens', { tokens });
}

watch(historyQuery, (value) => {
    if (historySearch.value !== value) {
        historySearch.value = value;
    }
});

function onHistorySearchInput(value: string): void {
    historySearch.value = value;
    setHistoryQuery(value);
}

function onHistoryScroll(event: Event): void {
    const el = event.target as HTMLElement | null;
    if (!el || !historyHasMore.value || loadingMoreHistory.value) {
        return;
    }

    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
        void loadMoreConversations();
    }
}

async function confirmDelete(id: string): Promise<void> {
    if (deletingId.value === id) {
        deletingId.value = null;
        await deleteConversation(id);

        return;
    }

    deletingId.value = id;
}
</script>

<template>
    <Sheet
        :open="available && open"
        @update:open="(value) => (!value ? closeChat() : undefined)"
    >
        <SheetContent
            :side="sheetSide"
            class="flex w-full max-w-[26rem] flex-col gap-0 border-border/30 bg-white p-0 shadow-[0_28px_70px_-30px_rgba(15,23,42,0.4)] sm:max-w-[26rem] dark:border-white/10 dark:bg-card"
        >
            <SheetHeader class="border-b border-border/30 px-4 py-3 text-start">
                <div class="flex items-center justify-between gap-2 pe-8">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <Button
                            v-if="view === 'history'"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 shrink-0 rounded-full"
                            :aria-label="t('Back')"
                            @click="showChat"
                        >
                            <ArrowLeft class="size-4 rtl:rotate-180" />
                        </Button>
                        <span
                            v-else
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand-accent)]/20 text-foreground"
                        >
                            <Sparkles class="size-3.5" :stroke-width="2" />
                        </span>
                        <SheetTitle class="truncate text-[15px] font-semibold tracking-tight">
                            {{ headerTitle }}
                        </SheetTitle>
                    </div>

                    <div class="flex shrink-0 items-center gap-0.5">
                        <Button
                            v-if="view === 'chat'"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 rounded-full text-muted-foreground hover:text-foreground"
                            :title="t('History')"
                            :aria-label="t('History')"
                            @click="showHistory"
                        >
                            <History class="size-3.5" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 rounded-full text-muted-foreground hover:text-foreground"
                            :title="t('New chat')"
                            :aria-label="t('New chat')"
                            @click="resetConversation"
                        >
                            <Plus class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </SheetHeader>

            <!-- History -->
            <div
                v-if="view === 'history'"
                class="flex min-h-0 flex-1 flex-col"
            >
                <div class="border-b border-border/30 px-3 py-2.5">
                    <label
                        class="flex items-center gap-2 rounded-xl bg-muted/50 px-3 py-2"
                    >
                        <Search class="size-3.5 shrink-0 text-muted-foreground" />
                        <input
                            :value="historySearch"
                            type="search"
                            class="w-full bg-transparent text-[13px] outline-none placeholder:text-muted-foreground/70"
                            :placeholder="t('Search chats…')"
                            @input="
                                onHistorySearchInput(
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <button
                            v-if="historySearch"
                            type="button"
                            class="rounded-full p-0.5 text-muted-foreground hover:text-foreground"
                            :aria-label="t('Clear')"
                            @click="onHistorySearchInput('')"
                        >
                            <X class="size-3.5" />
                        </button>
                    </label>
                </div>

                <div
                    ref="historyListEl"
                    class="flex min-h-0 flex-1 flex-col overflow-y-auto px-3 py-3"
                    @scroll="onHistoryScroll"
                >
                    <div
                        v-if="loadingHistory && conversations.length === 0"
                        class="flex flex-1 items-center justify-center gap-2 text-sm text-muted-foreground"
                    >
                        <LoaderCircle class="size-4 animate-spin" />
                        {{ t('Loading…') }}
                    </div>

                    <div
                        v-else-if="conversations.length === 0"
                        class="flex flex-1 flex-col items-center justify-center gap-3 px-4 text-center"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-2xl bg-muted text-foreground"
                        >
                            <History class="size-5" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-semibold">
                                {{
                                    historySearch
                                        ? t('No matching chats')
                                        : t('No chats yet')
                                }}
                            </p>
                            <p class="text-[12px] text-muted-foreground">
                                {{
                                    historySearch
                                        ? t('Try a different search.')
                                        : t(
                                              'Start a task and it will show up here.',
                                          )
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="!historySearch"
                            type="button"
                            size="sm"
                            class="mt-1 rounded-full bg-[var(--brand-accent)] text-zinc-900 hover:brightness-95"
                            @click="resetConversation"
                        >
                            <MessageSquarePlus class="size-3.5" />
                            {{ t('New chat') }}
                        </Button>
                    </div>

                    <div v-else class="space-y-1">
                        <div
                            v-for="item in conversations"
                            :key="item.id"
                            class="group flex w-full items-center gap-1 rounded-xl px-2 py-2 transition hover:bg-muted/50"
                            :class="
                                item.id === conversationId
                                    ? 'bg-muted/60'
                                    : ''
                            "
                        >
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-2.5 text-start"
                                @click="openConversation(item.id)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="truncate text-[13px] font-medium text-foreground"
                                        >
                                            {{ item.title }}
                                        </span>
                                        <span
                                            class="shrink-0 text-[10px] text-muted-foreground"
                                        >
                                            {{ formatWhen(item.updated_at) }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="item.preview"
                                        class="mt-0.5 line-clamp-1 text-[11px] text-muted-foreground"
                                    >
                                        {{ item.preview }}
                                    </span>
                                </span>
                            </button>
                            <button
                                type="button"
                                class="size-7 shrink-0 rounded-full text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive sm:opacity-0 sm:group-hover:opacity-100"
                                :class="
                                    deletingId === item.id
                                        ? 'bg-destructive/10 text-destructive opacity-100'
                                        : ''
                                "
                                :aria-label="
                                    deletingId === item.id
                                        ? t('Confirm delete')
                                        : t('Delete')
                                "
                                :title="
                                    deletingId === item.id
                                        ? t('Tap again to delete')
                                        : t('Delete')
                                "
                                @click="confirmDelete(item.id)"
                            >
                                <Trash2 class="mx-auto size-3.5" />
                            </button>
                        </div>

                        <div
                            v-if="loadingMoreHistory"
                            class="flex items-center justify-center gap-2 py-3 text-[12px] text-muted-foreground"
                        >
                            <LoaderCircle class="size-3.5 animate-spin" />
                            {{ t('Loading…') }}
                        </div>
                        <p
                            v-else-if="historyHasMore"
                            class="py-2 text-center text-[11px] text-muted-foreground"
                        >
                            {{ t('Scroll for more') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Chat -->
            <template v-else>
                <div
                    ref="listEl"
                    class="flex min-h-0 flex-1 flex-col overflow-y-auto"
                >
                    <!-- Empty / ready state -->
                    <div
                        v-if="messages.length === 0"
                        class="flex flex-1 flex-col px-5 pb-4 pt-4"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-[17px] font-semibold tracking-tight text-foreground">
                                {{ greeting }}
                            </p>
                            <p class="mt-0.5 text-[12px] text-muted-foreground">
                                {{ t('AI assistant ready to help') }}
                            </p>
                        </div>

                        <div
                            class="flex flex-1 flex-col items-center justify-center gap-5 py-6"
                        >
                            <div
                                class="flex items-end justify-center gap-2.5 py-5 rtl:flex-row-reverse"
                            >
                                <AiDotIcon size="lg" variant="rose" :playing="true" />
                                <AiDotIcon size="lg" variant="sky" :playing="true" />
                                <AiDotIcon size="lg" variant="amber" :playing="true" />
                                <AiDotIcon size="lg" variant="lime" :playing="true" />
                                <AiDotIcon size="lg" variant="violet" :playing="true" />
                            </div>

                            <div class="space-y-1 text-center">
                                <p class="text-[15px] font-semibold tracking-tight text-foreground">
                                    {{ t('AI is ready') }}
                                </p>
                                <p class="max-w-[16rem] text-[12px] leading-relaxed text-muted-foreground">
                                    {{ t('Waiting for your instructions.') }}
                                </p>
                            </div>

                            <div class="flex max-w-full flex-wrap justify-center gap-2 pt-1">
                                <button
                                    v-for="action in quickActions"
                                    :key="action.id"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-[var(--brand-accent)]/15 px-3.5 py-2 text-[12px] font-medium text-foreground transition hover:bg-[var(--brand-accent)]/28"
                                    :disabled="sending"
                                    @click="sendMessage(action.prompt)"
                                >
                                    <component
                                        :is="action.icon"
                                        class="size-3.5"
                                        :class="action.tone"
                                        :stroke-width="2"
                                    />
                                    {{ action.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div
                        v-else
                        class="flex flex-col gap-3 px-4 py-4"
                    >
                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="flex flex-col gap-2"
                            :class="
                                message.role === 'user'
                                    ? 'items-end'
                                    : 'items-start'
                            "
                        >
                            <div
                                v-if="message.role === 'assistant'"
                                class="mb-0.5 flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground"
                            >
                                <span
                                    class="flex size-5 items-center justify-center rounded-full bg-[var(--brand-accent)]/20 text-foreground"
                                >
                                    <Sparkles class="size-3" :stroke-width="2" />
                                </span>
                                {{ t('Agent') }}
                            </div>

                            <div
                                class="max-w-[92%] px-3.5 py-2.5 text-[13px] leading-relaxed"
                                :class="{
                                    'rounded-2xl rounded-ee-md bg-primary text-primary-foreground shadow-[0_12px_28px_-18px_rgba(15,23,42,0.55)]':
                                        message.role === 'user',
                                    'rounded-2xl rounded-es-md border border-border/60 bg-muted/30 text-foreground':
                                        message.role === 'assistant',
                                    'rounded-2xl border border-destructive/25 bg-destructive/[0.06] text-destructive':
                                        message.role === 'system',
                                }"
                            >
                                <div
                                    v-if="message.attachments?.length"
                                    class="mb-2 flex flex-wrap gap-1.5"
                                >
                                    <span
                                        v-for="(file, fileIndex) in message.attachments"
                                        :key="`${message.id}-file-${fileIndex}`"
                                        class="inline-flex max-w-full items-center gap-1 truncate rounded-full px-2 py-0.5 text-[10px] font-medium"
                                        :class="
                                            message.role === 'user'
                                                ? 'bg-white/15 text-white'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        <Paperclip class="size-2.5 shrink-0" />
                                        <span class="truncate">{{
                                            file.name
                                        }}</span>
                                    </span>
                                </div>
                                <AiChatMessageContent
                                    v-if="message.content"
                                    :content="message.content"
                                    :tone="message.role"
                                    @navigate="closeChat"
                                />
                                <template v-else>
                                    {{ t('Waiting for approval…') }}
                                </template>
                            </div>

                            <div
                                v-if="message.links?.length"
                                class="flex max-w-[92%] flex-wrap gap-1.5"
                            >
                                <Link
                                    v-for="(link, linkIndex) in message.links"
                                    :key="`${message.id}-link-${linkIndex}`"
                                    :href="link.href"
                                    class="inline-flex items-center rounded-full border border-border/70 bg-background px-3 py-1 text-[11px] font-semibold text-foreground transition hover:bg-muted"
                                    @click="closeChat"
                                >
                                    {{ link.label }}
                                </Link>
                            </div>

                            <ol
                                v-if="message.steps?.length"
                                class="w-full max-w-[92%] space-y-1.5 rounded-2xl border border-border/50 bg-muted/20 px-3 py-2.5"
                            >
                                <li
                                    class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t('Steps') }}
                                </li>
                                <li
                                    v-for="(step, stepIndex) in message.steps"
                                    :key="`${message.id}-step-${stepIndex}`"
                                    class="flex items-start gap-2 text-[12px] leading-snug"
                                >
                                    <component
                                        :is="stepStatusIcon(step.status)"
                                        class="mt-0.5 size-3.5 shrink-0"
                                        :class="{
                                            'text-[var(--brand-accent)]':
                                                step.status === 'done',
                                            'text-destructive':
                                                step.status === 'failed' ||
                                                step.status === 'denied',
                                            'text-amber-600 dark:text-amber-400':
                                                step.status ===
                                                'awaiting_approval',
                                            'animate-spin text-muted-foreground':
                                                step.status === 'awaiting',
                                            'text-muted-foreground': ![
                                                'done',
                                                'failed',
                                                'denied',
                                                'awaiting_approval',
                                                'awaiting',
                                            ].includes(step.status),
                                        }"
                                    />
                                    <div class="min-w-0">
                                        <p class="font-medium text-foreground">
                                            {{ step.summary || step.label }}
                                        </p>
                                        <p
                                            v-if="
                                                step.detail &&
                                                step.status !== 'done'
                                            "
                                            class="mt-0.5 truncate text-[11px] text-muted-foreground"
                                        >
                                            {{ step.detail }}
                                        </p>
                                    </div>
                                </li>
                            </ol>
                        </div>

                        <div
                            v-if="sending"
                            class="flex items-center justify-between gap-2 text-[12px] text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-2">
                                <LoaderCircle
                                    class="size-3.5 animate-spin text-foreground"
                                />
                                {{ t('Thinking…') }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-background px-2.5 py-1 text-[11px] font-medium text-foreground transition hover:bg-muted"
                                @click="stopAgent"
                            >
                                <Square
                                    class="size-2.5 fill-current"
                                    :stroke-width="0"
                                />
                                {{ t('Stop') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="activeApprovals.length"
                    class="border-t border-border/30 px-3 py-3"
                >
                    <div
                        class="rounded-2xl border border-border/70 bg-card px-3.5 py-3 shadow-soft"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <p
                                    class="text-[12px] font-semibold tracking-tight text-foreground"
                                >
                                    {{ t('Approval needed') }}
                                </p>
                                <p
                                    v-for="approval in activeApprovals"
                                    :key="approval.id"
                                    class="text-[12px] leading-snug text-muted-foreground"
                                >
                                    {{ approvalHeadline(approval) }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 flex-1 rounded-full bg-[var(--brand-accent)] text-zinc-900 hover:brightness-95"
                                :disabled="sending"
                                @click="approveAll(activeApprovals)"
                            >
                                {{ t('Approve') }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 flex-1 rounded-full"
                                :disabled="sending"
                                @click="rejectAll(activeApprovals)"
                            >
                                {{ t('Cancel') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <form
                    class="border-t border-border/30 p-3"
                    @submit.prevent="sendMessage()"
                >
                    <input
                        ref="fileInputEl"
                        type="file"
                        class="hidden"
                        multiple
                        :accept="CHAT_FILE_ACCEPT"
                        @change="onFilesSelected"
                    />

                    <div
                        v-if="pendingFiles.length"
                        class="mb-2 flex flex-wrap gap-1.5"
                    >
                        <span
                            v-for="(file, index) in pendingFiles"
                            :key="`${file.name}-${index}`"
                            class="inline-flex max-w-full items-center gap-1 rounded-full bg-muted/60 py-1 ps-2.5 pe-1 text-[11px]"
                        >
                            <FileText class="size-3 shrink-0 text-muted-foreground" />
                            <span class="truncate font-medium">{{
                                file.name
                            }}</span>
                            <button
                                type="button"
                                class="rounded-full p-0.5 text-muted-foreground hover:bg-background hover:text-foreground"
                                :aria-label="t('Remove')"
                                @click="removePendingFile(index)"
                            >
                                <X class="size-3" />
                            </button>
                        </span>
                    </div>

                    <div
                        class="rounded-[1.35rem] bg-zinc-900/[0.04] p-2 dark:bg-white/[0.06]"
                    >
                        <div
                            v-if="activeContext || livePageContext"
                            class="mb-1.5 flex flex-wrap items-center gap-1.5 px-1.5 pt-0.5"
                        >
                            <span
                                v-if="activeContext"
                                class="inline-flex max-w-full items-center gap-1 rounded-md border border-border/60 bg-background px-2 py-1 text-[11px] text-foreground"
                            >
                                <AtSign
                                    class="size-3 shrink-0 text-muted-foreground"
                                    :stroke-width="2"
                                />
                                <span class="truncate font-medium">{{
                                    activeContext.label
                                }}</span>
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    :aria-label="t('Remove context')"
                                    @click="dismissContext"
                                >
                                    <X class="size-3" />
                                </button>
                            </span>
                            <button
                                v-else-if="livePageContext"
                                type="button"
                                class="inline-flex max-w-full items-center gap-1 rounded-md border border-dashed border-border/70 px-2 py-1 text-[11px] text-muted-foreground transition hover:border-border hover:bg-background hover:text-foreground"
                                @click="restoreContext"
                            >
                                <AtSign class="size-3 shrink-0" :stroke-width="2" />
                                <span class="truncate">{{
                                    t('Add :label', {
                                        label: livePageContext.label,
                                    })
                                }}</span>
                            </button>
                        </div>
                        <textarea
                            ref="inputEl"
                            v-model="draft"
                            rows="2"
                            class="max-h-28 min-h-[2.75rem] w-full resize-none bg-transparent px-2.5 py-1.5 text-[13px] leading-relaxed outline-none placeholder:text-muted-foreground/65"
                            :placeholder="t('Ask AI or give instructions…')"
                            :disabled="sending"
                            @keydown="onKeydown"
                        />
                        <div class="mt-1 flex items-center justify-between gap-2 px-0.5">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-8 rounded-full px-2.5 text-muted-foreground hover:text-foreground"
                                :disabled="sending"
                                :aria-label="t('Attach file')"
                                @click="openFilePicker"
                            >
                                <Paperclip class="size-3.5" />
                                <span class="text-[11px] font-medium">{{
                                    t('Attach')
                                }}</span>
                            </Button>
                            <Button
                                v-if="sending"
                                type="button"
                                size="icon"
                                class="size-9 shrink-0 rounded-full bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                :aria-label="t('Stop')"
                                @click="stopAgent"
                            >
                                <Square
                                    class="size-3 fill-current"
                                    :stroke-width="0"
                                />
                            </Button>
                            <Button
                                v-else
                                type="submit"
                                size="icon"
                                class="size-9 shrink-0 rounded-full bg-[var(--brand-accent)] text-zinc-900 shadow-[0_10px_24px_-12px_color-mix(in_srgb,var(--brand-accent)_55%,transparent)] hover:brightness-95 disabled:opacity-40"
                                :disabled="!canSend()"
                                :aria-label="t('Send')"
                            >
                                <Send class="size-3.5 rtl:-scale-x-100" />
                            </Button>
                        </div>
                    </div>
                    <p
                        v-if="conversationUsage.total_tokens > 0"
                        class="mt-2 px-1 text-start text-[10px] text-muted-foreground"
                    >
                        {{ usageLabel(conversationUsage) }}
                    </p>
                </form>
            </template>
        </SheetContent>
    </Sheet>
</template>
