<script setup lang="ts">
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    Check,
    CheckCheck,
    CheckCircle2,
    Circle,
    Info,
    LoaderCircle,
    Trash2,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useNotifications } from '@/composables/useNotifications';
import { useTranslations } from '@/composables/useTranslations';
import type { AppNotification, NotificationType } from '@/types/notifications';

const typeStyles: Record<
    NotificationType,
    { icon: typeof Info; iconClass: string }
> = {
    info: {
        icon: Info,
        iconClass:
            'text-sky-700 bg-sky-100/80 dark:bg-sky-950/50 dark:text-sky-300',
    },
    success: {
        icon: CheckCircle2,
        iconClass:
            'text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] bg-[var(--brand-accent)]/15 dark:bg-[var(--brand-accent)]/20 dark:text-[var(--brand-accent)]',
    },
    warning: {
        icon: AlertTriangle,
        iconClass:
            'text-amber-700 bg-amber-100/80 dark:bg-amber-950/50 dark:text-amber-300',
    },
    error: {
        icon: AlertCircle,
        iconClass:
            'text-red-700 bg-red-100/80 dark:bg-red-950/50 dark:text-red-300',
    },
};

const {
    items,
    loading,
    open,
    hasUnread,
    hasItems,
    openPanel,
    closePanel,
    markAsRead,
    markAsUnread,
    markAllAsRead,
    deleteNotification,
    clearAll,
    handleNotificationClick,
} = useNotifications();

const { t } = useTranslations();

function onOpenChange(isOpen: boolean): void {
    if (isOpen) {
        void openPanel();
    } else {
        closePanel();
    }
}

function notificationIcon(type: NotificationType) {
    return typeStyles[type]?.icon ?? Info;
}

function notificationIconClass(type: NotificationType) {
    return typeStyles[type]?.iconClass ?? typeStyles.info.iconClass;
}

function onClick(notification: AppNotification): void {
    void handleNotificationClick(notification);
}

function onToggleRead(event: Event, notification: AppNotification): void {
    event.stopPropagation();
    if (notification.read_at) {
        void markAsUnread(notification.id);
    } else {
        void markAsRead(notification.id);
    }
}

function onDelete(event: Event, id: string): void {
    event.stopPropagation();
    void deleteNotification(id);
}

function onClearAll(): void {
    void clearAll();
}

function onMarkAllRead(): void {
    void markAllAsRead();
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
        return `${Math.max(1, Math.floor(diffMs / minute))}m`;
    }
    if (diffMs < day) {
        return `${Math.max(1, Math.floor(diffMs / hour))}h`;
    }
    if (diffMs < 7 * day) {
        return `${Math.max(1, Math.floor(diffMs / day))}d`;
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <DropdownMenu :open="open" @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative size-9 rounded-full text-zinc-700 hover:bg-white/70 hover:text-zinc-900 dark:text-zinc-200 dark:hover:bg-white/10 dark:hover:text-white"
            >
                <Bell class="size-4" stroke-width="1.75" />
                <span
                    v-if="hasUnread"
                    class="absolute end-1.5 top-1.5 size-2 rounded-full bg-[var(--brand-accent)] ring-2 ring-white dark:ring-zinc-900"
                />
                <span class="sr-only">{{ t('Notifications') }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :side-offset="10"
            :collision-padding="16"
            class="w-[22rem] overflow-hidden rounded-2xl border-border/50 bg-white/95 p-0 shadow-[0_18px_40px_-20px_rgba(15,23,42,0.35)] backdrop-blur-xl dark:border-white/10 dark:bg-card/95"
        >
            <div class="flex items-center justify-between gap-2 px-3.5 pt-3 pb-2">
                <p class="text-sm font-semibold">{{ t('Notifications') }}</p>
                <div v-if="hasItems" class="flex items-center gap-1">
                    <button
                        v-if="hasUnread"
                        type="button"
                        class="inline-flex size-7 items-center justify-center rounded-full text-muted-foreground transition hover:bg-zinc-900/5 hover:text-foreground dark:hover:bg-white/10"
                        :title="t('Mark all as read')"
                        :aria-label="t('Mark all as read')"
                        @click="onMarkAllRead"
                    >
                        <CheckCheck class="size-3.5" stroke-width="1.75" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-7 items-center justify-center rounded-full text-muted-foreground transition hover:bg-red-500/10 hover:text-red-600"
                        :title="t('Clear all')"
                        :aria-label="t('Clear all')"
                        @click="onClearAll"
                    >
                        <Trash2 class="size-3.5" stroke-width="1.75" />
                    </button>
                </div>
            </div>

            <div v-if="loading" class="flex justify-center py-8">
                <LoaderCircle class="size-4 animate-spin text-muted-foreground" />
            </div>

            <div
                v-else-if="!hasItems"
                class="flex flex-col items-center gap-2 px-3.5 py-10 text-center"
            >
                <span
                    class="inline-flex size-10 items-center justify-center rounded-full bg-zinc-900/5 text-muted-foreground dark:bg-white/10"
                >
                    <Bell class="size-4" stroke-width="1.75" />
                </span>
                <p class="text-sm text-muted-foreground">
                    {{ t('No notifications') }}
                </p>
            </div>

            <div v-else class="max-h-80 space-y-0.5 overflow-y-auto px-1.5 pb-1.5">
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="group relative flex cursor-pointer items-start gap-2.5 rounded-xl px-2.5 py-2.5 transition hover:bg-zinc-900/[0.04] dark:hover:bg-white/[0.05]"
                    :class="!item.read_at ? 'bg-zinc-900/[0.03] dark:bg-white/[0.03]' : ''"
                    role="button"
                    tabindex="0"
                    @click="onClick(item)"
                    @keydown.enter.prevent="onClick(item)"
                >
                    <div
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                        :class="notificationIconClass(item.type)"
                    >
                        <component
                            :is="notificationIcon(item.type)"
                            class="size-3.5"
                            stroke-width="1.75"
                        />
                    </div>

                    <div class="min-w-0 flex-1 text-start">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="truncate text-[13px] leading-snug"
                                :class="
                                    item.read_at
                                        ? 'text-muted-foreground'
                                        : 'font-medium text-foreground'
                                "
                            >
                                {{ item.title }}
                            </p>
                            <span
                                class="shrink-0 pt-0.5 text-[10px] text-muted-foreground"
                            >
                                {{ formatWhen(item.created_at) }}
                            </span>
                        </div>
                        <p
                            v-if="item.body"
                            class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-muted-foreground"
                        >
                            {{ item.body }}
                        </p>

                        <div
                            class="mt-1.5 flex items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover:opacity-100"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium text-muted-foreground transition hover:bg-zinc-900/5 hover:text-foreground dark:hover:bg-white/10"
                                :title="
                                    item.read_at
                                        ? t('Mark as unread')
                                        : t('Mark as read')
                                "
                                @click="onToggleRead($event, item)"
                            >
                                <component
                                    :is="item.read_at ? Circle : Check"
                                    class="size-3"
                                    stroke-width="1.75"
                                />
                                {{
                                    item.read_at
                                        ? t('Unread')
                                        : t('Read')
                                }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium text-muted-foreground transition hover:bg-red-500/10 hover:text-red-600"
                                :title="t('Delete')"
                                @click="onDelete($event, item.id)"
                            >
                                <Trash2 class="size-3" stroke-width="1.75" />
                                {{ t('Delete') }}
                            </button>
                        </div>
                    </div>

                    <span
                        v-if="!item.read_at"
                        class="mt-2 size-1.5 shrink-0 rounded-full bg-[var(--brand-accent)]"
                    />
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
