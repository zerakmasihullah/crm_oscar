<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileText,
    MessageCircle,
    Paperclip,
    Search,
    Send,
    X,
} from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import { useTranslations } from '@/composables/useTranslations';

type ChatUser = {
    id: number;
    name: string;
    email: string;
    phone_number?: string | null;
    unread_count: number;
    last_message_preview?: string | null;
    last_message_label?: string | null;
};

type ChatMessage = {
    id: number;
    sender: number;
    receiver: number;
    message: string;
    attachment_url?: string | null;
    created_at?: string | null;
    sender_name?: string | null;
};

const props = defineProps<{
    users: ChatUser[];
    selectedUserId: number | null;
    selectedUser: {
        id: number;
        name: string;
        email: string;
        phone_number?: string | null;
    } | null;
    messages: ChatMessage[];
    filters?: { search?: string | null };
    unreadTotal: number;
    authUserId: number;
}>();

const { t } = useTranslations();
const { getInitials } = useInitials();
const isMobile = useMediaQuery('(max-width: 767px)');

const search = ref(props.filters?.search ?? '');
const messagesEl = ref<HTMLElement | null>(null);
const composerEl = ref<HTMLTextAreaElement | null>(null);
const fileInputEl = ref<HTMLInputElement | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const form = useForm({
    message: '',
    attachment: null as File | null,
});

const showThread = computed(() => Boolean(props.selectedUserId));

watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        const current = (props.filters?.search ?? '').trim();
        if (next === current) return;
        router.get(
            '/chats',
            {
                search: next || undefined,
                user: props.selectedUserId || undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 300);
});

async function scrollToBottom(smooth = false): Promise<void> {
    await nextTick();
    const el = messagesEl.value;
    if (!el) return;
    if (smooth) {
        el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
        return;
    }
    el.scrollTop = el.scrollHeight;
}

watch(
    () => props.messages,
    () => {
        void scrollToBottom();
    },
    { deep: true },
);

watch(
    () => props.selectedUserId,
    () => {
        void scrollToBottom();
        if (!isMobile.value) {
            void nextTick(() => composerEl.value?.focus());
        }
    },
);

onMounted(() => {
    void scrollToBottom();
});

function selectUser(userId: number): void {
    router.get(
        '/chats',
        {
            user: userId,
            search: search.value.trim() || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function clearSelection(): void {
    router.get(
        '/chats',
        {
            search: search.value.trim() || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function onAttachment(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.attachment = input.files?.[0] ?? null;
}

function clearAttachment(): void {
    form.attachment = null;
    if (fileInputEl.value) {
        fileInputEl.value.value = '';
    }
}

function canSend(): boolean {
    return Boolean(
        props.selectedUserId && form.message.trim() && !form.processing,
    );
}

function send(): void {
    if (!canSend() || !props.selectedUserId) return;

    form.post(`/chats/${props.selectedUserId}/messages`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('message', 'attachment');
            if (fileInputEl.value) {
                fileInputEl.value.value = '';
            }
            void nextTick(() => {
                resizeComposer();
                if (!isMobile.value) {
                    composerEl.value?.focus();
                }
                void scrollToBottom(true);
            });
        },
    });
}

function onComposerKeydown(event: KeyboardEvent): void {
    // On mobile, Enter should insert a newline (native keyboard behavior).
    if (isMobile.value || event.key !== 'Enter' || event.shiftKey) return;
    event.preventDefault();
    send();
}

function resizeComposer(): void {
    const el = composerEl.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, isMobile.value ? 120 : 140)}px`;
}

function formatMessageTime(value?: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const now = new Date();
    const sameDay =
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate();

    if (sameDay) {
        return date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function messageDayKey(value?: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toDateString();
}

function dayLabel(value?: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const now = new Date();
    const startToday = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
    );
    const startMsg = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );
    const diffDays = Math.round(
        (startToday.getTime() - startMsg.getTime()) / 86_400_000,
    );

    if (diffDays === 0) return t('Today');
    if (diffDays === 1) return t('Yesterday');

    return date.toLocaleDateString([], {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric',
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Chat', href: '/chats' }],
    },
});
</script>

<template>
    <Head :title="t('Customer Chat')" />

    <div
        class="flex min-h-0 flex-1 overflow-hidden border-t border-zinc-900/5 bg-white/80 backdrop-blur-xl dark:border-white/10 dark:bg-card/40"
    >
        <!-- Conversation list -->
        <aside
            class="flex min-h-0 w-full flex-col border-zinc-900/5 dark:border-white/10 md:w-[22rem] md:shrink-0 md:border-e lg:w-[24rem]"
            :class="showThread ? 'hidden md:flex' : 'flex'"
        >
            <div
                class="shrink-0 space-y-3 border-b border-zinc-900/5 px-3 py-3 sm:px-4 sm:py-4 dark:border-white/10"
            >
                <div class="min-w-0 md:block">
                    <h1
                        class="truncate text-base font-semibold tracking-tight text-zinc-900 dark:text-zinc-50"
                    >
                        {{ t('Customer Chat') }}
                    </h1>
                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                        <template v-if="unreadTotal > 0">
                            {{ unreadTotal }} {{ t('unread') }}
                        </template>
                        <template v-else>
                            {{ t('Message customers and staff.') }}
                        </template>
                    </p>
                </div>

                <div class="relative">
                    <Search
                        class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400"
                        stroke-width="1.75"
                    />
                    <input
                        v-model="search"
                        type="search"
                        enterkeyhint="search"
                        autocomplete="off"
                        class="h-11 w-full rounded-full border-0 bg-zinc-900/[0.04] pe-3 ps-9 text-base text-zinc-900 outline-none ring-1 ring-transparent transition placeholder:text-zinc-400 focus:bg-white focus:ring-zinc-900/10 sm:h-10 sm:text-sm dark:bg-white/5 dark:text-zinc-100 dark:focus:bg-white/10 dark:focus:ring-white/15"
                        :placeholder="t('Search…')"
                    />
                </div>
            </div>

            <ul
                class="min-h-0 flex-1 touch-pan-y overflow-y-auto overscroll-contain px-1.5 py-2 sm:px-2"
            >
                <li v-for="user in users" :key="user.id">
                    <button
                        type="button"
                        class="flex min-h-14 w-full items-center gap-3 rounded-2xl px-3 py-3 text-start transition active:scale-[0.99] sm:min-h-0 sm:items-start sm:py-2.5"
                        :class="
                            selectedUserId === user.id
                                ? 'bg-zinc-900 text-white shadow-[0_12px_28px_-18px_rgba(15,23,42,0.55)] dark:bg-white dark:text-zinc-900'
                                : 'text-zinc-800 hover:bg-zinc-900/[0.04] dark:text-zinc-100 dark:hover:bg-white/5'
                        "
                        @click="selectUser(user.id)"
                    >
                        <Avatar class="size-11 shrink-0 sm:mt-0.5 sm:size-10">
                            <AvatarFallback
                                class="text-xs font-semibold"
                                :class="
                                    selectedUserId === user.id
                                        ? 'bg-white/15 text-white dark:bg-zinc-900/10 dark:text-zinc-900'
                                        : 'bg-zinc-900/5 text-zinc-700 dark:bg-white/10 dark:text-zinc-200'
                                "
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>

                        <span class="min-w-0 flex-1">
                            <span
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="truncate text-[15px] font-medium sm:text-sm">
                                    {{ user.name }}
                                </span>
                                <span
                                    class="shrink-0 text-[11px]"
                                    :class="
                                        selectedUserId === user.id
                                            ? 'text-white/65 dark:text-zinc-500'
                                            : 'text-zinc-400'
                                    "
                                >
                                    {{ user.last_message_label || '' }}
                                </span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-2">
                                <span
                                    class="min-w-0 flex-1 truncate text-[13px] leading-snug sm:text-[12px]"
                                    :class="
                                        selectedUserId === user.id
                                            ? 'text-white/70 dark:text-zinc-500'
                                            : user.unread_count > 0
                                              ? 'font-medium text-zinc-700 dark:text-zinc-200'
                                              : 'text-zinc-500 dark:text-zinc-400'
                                    "
                                >
                                    {{
                                        user.last_message_preview || user.email
                                    }}
                                </span>
                                <span
                                    v-if="user.unread_count > 0"
                                    class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-semibold"
                                    :class="
                                        selectedUserId === user.id
                                            ? 'bg-white text-zinc-900 dark:bg-zinc-900 dark:text-white'
                                            : 'bg-destructive text-destructive-foreground'
                                    "
                                >
                                    {{
                                        user.unread_count > 99
                                            ? '99+'
                                            : user.unread_count
                                    }}
                                </span>
                            </span>
                        </span>
                    </button>
                </li>

                <li
                    v-if="!users.length"
                    class="flex flex-col items-center gap-2 px-4 py-16 text-center"
                >
                    <MessageCircle
                        class="size-8 text-zinc-300 dark:text-zinc-600"
                        stroke-width="1.5"
                    />
                    <p class="text-sm text-zinc-500">{{ t('No results') }}</p>
                </li>
            </ul>
        </aside>

        <!-- Thread -->
        <section
            class="flex min-h-0 min-w-0 flex-1 flex-col bg-zinc-50/70 dark:bg-transparent"
            :class="showThread ? 'flex' : 'hidden md:flex'"
        >
            <template v-if="selectedUser">
                <header
                    class="flex shrink-0 items-center gap-2 border-b border-zinc-900/5 bg-white/95 px-2 py-2.5 backdrop-blur-xl sm:gap-3 sm:px-5 sm:py-3 dark:border-white/10 dark:bg-card/70"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-11 shrink-0 rounded-full touch-manipulation md:hidden"
                        :aria-label="t('Back')"
                        @click="clearSelection"
                    >
                        <ArrowLeft
                            class="size-5 rtl:rotate-180"
                            stroke-width="1.75"
                        />
                    </Button>

                    <Avatar class="size-10 shrink-0">
                        <AvatarFallback
                            class="bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900"
                        >
                            {{ getInitials(selectedUser.name) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-[15px] font-semibold text-zinc-900 sm:text-sm dark:text-zinc-50"
                        >
                            {{ selectedUser.name }}
                        </p>
                        <p
                            class="truncate text-xs text-zinc-500 dark:text-zinc-400"
                        >
                            <span class="sm:hidden">
                                {{
                                    selectedUser.phone_number ||
                                    selectedUser.email
                                }}
                            </span>
                            <span class="hidden sm:inline">
                                {{ selectedUser.email }}
                                <template v-if="selectedUser.phone_number">
                                    · {{ selectedUser.phone_number }}
                                </template>
                            </span>
                        </p>
                    </div>
                </header>

                <div
                    ref="messagesEl"
                    class="min-h-0 flex-1 touch-pan-y overflow-y-auto overscroll-contain px-3 py-3 sm:px-6 sm:py-4"
                >
                    <div
                        v-if="!messages.length"
                        class="flex h-full min-h-[12rem] flex-col items-center justify-center gap-2 px-4 text-center"
                    >
                        <MessageCircle
                            class="size-9 text-zinc-300 dark:text-zinc-600"
                            stroke-width="1.5"
                        />
                        <p class="text-sm text-zinc-500">
                            {{ t('No messages yet.') }}
                        </p>
                    </div>

                    <div
                        v-else
                        class="mx-auto flex w-full max-w-3xl flex-col gap-1.5"
                    >
                        <template
                            v-for="(message, index) in messages"
                            :key="message.id"
                        >
                            <div
                                v-if="
                                    messageDayKey(message.created_at) !==
                                    messageDayKey(
                                        messages[index - 1]?.created_at,
                                    )
                                "
                                class="my-2 flex justify-center sm:my-3"
                            >
                                <span
                                    class="rounded-full bg-zinc-900/5 px-3 py-1 text-[11px] font-medium text-zinc-500 dark:bg-white/10 dark:text-zinc-400"
                                >
                                    {{ dayLabel(message.created_at) }}
                                </span>
                            </div>

                            <div
                                class="flex"
                                :class="
                                    message.sender === authUserId
                                        ? 'justify-end'
                                        : 'justify-start'
                                "
                            >
                                <div
                                    class="max-w-[88%] rounded-[1.25rem] px-3.5 py-2.5 text-[15px] leading-relaxed shadow-sm sm:max-w-[min(100%,28rem)]"
                                    :class="
                                        message.sender === authUserId
                                            ? 'rounded-br-md bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                            : 'rounded-bl-md border border-zinc-900/5 bg-white text-zinc-800 dark:border-white/10 dark:bg-card dark:text-zinc-100'
                                    "
                                >
                                    <p class="whitespace-pre-wrap break-words">
                                        {{ message.message }}
                                    </p>

                                    <a
                                        v-if="message.attachment_url"
                                        :href="message.attachment_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-2 inline-flex min-h-8 items-center gap-1.5 text-xs font-medium underline-offset-2 hover:underline"
                                        :class="
                                            message.sender === authUserId
                                                ? 'text-white/85 dark:text-zinc-600'
                                                : 'text-zinc-600 dark:text-zinc-300'
                                        "
                                    >
                                        <FileText class="size-3.5 shrink-0" />
                                        {{ t('Attachment') }}
                                    </a>

                                    <p
                                        class="mt-1.5 text-[10px] tabular-nums"
                                        :class="
                                            message.sender === authUserId
                                                ? 'text-white/55 dark:text-zinc-500'
                                                : 'text-zinc-400'
                                        "
                                    >
                                        {{
                                            formatMessageTime(
                                                message.created_at,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <Can permission="chats.create">
                    <form
                        class="shrink-0 border-t border-zinc-900/5 bg-white/95 px-2 pt-2 backdrop-blur-xl sm:px-5 sm:py-3 dark:border-white/10 dark:bg-card/70"
                        style="
                            padding-bottom: max(
                                0.5rem,
                                env(safe-area-inset-bottom, 0px)
                            );
                        "
                        @submit.prevent="send"
                    >
                        <div
                            v-if="form.attachment"
                            class="mb-2 flex items-center gap-2 rounded-xl bg-zinc-900/[0.04] px-3 py-2 text-xs text-zinc-600 dark:bg-white/5 dark:text-zinc-300"
                        >
                            <Paperclip class="size-3.5 shrink-0" />
                            <span class="min-w-0 flex-1 truncate">{{
                                form.attachment.name
                            }}</span>
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full touch-manipulation hover:bg-zinc-900/10 dark:hover:bg-white/10"
                                :aria-label="t('Remove')"
                                @click="clearAttachment"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>

                        <div
                            class="mx-auto flex w-full max-w-3xl items-end gap-1 rounded-[1.5rem] bg-zinc-900/[0.04] p-1 ring-1 ring-zinc-900/5 sm:gap-2 sm:p-1.5 dark:bg-white/5 dark:ring-white/10"
                        >
                            <label
                                class="inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-zinc-500 transition touch-manipulation hover:bg-white hover:text-zinc-900 sm:size-10 dark:hover:bg-white/10 dark:hover:text-white"
                                :title="t('Attachment')"
                            >
                                <Paperclip
                                    class="size-4"
                                    stroke-width="1.75"
                                />
                                <input
                                    ref="fileInputEl"
                                    type="file"
                                    class="hidden"
                                    @change="onAttachment"
                                />
                            </label>

                            <textarea
                                ref="composerEl"
                                v-model="form.message"
                                rows="1"
                                enterkeyhint="send"
                                class="max-h-[120px] min-h-11 flex-1 resize-none bg-transparent py-2.5 text-base leading-relaxed text-zinc-900 outline-none placeholder:text-zinc-400 sm:min-h-10 sm:max-h-[140px] sm:text-[15px] dark:text-zinc-100"
                                :placeholder="t('Message')"
                                @keydown="onComposerKeydown"
                                @input="resizeComposer"
                                @focus="() => scrollToBottom()"
                            />

                            <Button
                                type="submit"
                                size="icon"
                                class="size-11 shrink-0 rounded-full touch-manipulation sm:size-10"
                                :disabled="!canSend()"
                                :aria-label="t('Send')"
                            >
                                <Send class="size-4" stroke-width="1.75" />
                            </Button>
                        </div>
                    </form>
                </Can>
            </template>

            <div
                v-else
                class="flex h-full flex-col items-center justify-center gap-3 px-6 text-center"
            >
                <span
                    class="inline-flex size-14 items-center justify-center rounded-full bg-zinc-900/5 text-zinc-400 dark:bg-white/5"
                >
                    <MessageCircle class="size-6" stroke-width="1.5" />
                </span>
                <div class="space-y-1">
                    <p
                        class="text-sm font-medium text-zinc-800 dark:text-zinc-100"
                    >
                        {{ t('Select a conversation') }}
                    </p>
                    <p class="max-w-xs text-sm text-zinc-500">
                        {{ t('Select a user to chat with.') }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
