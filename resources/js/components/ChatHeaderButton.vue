<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const { t } = useTranslations();
const { can } = usePermissions();
const { isCurrentOrParentUrl } = useCurrentUrl();
const page = usePage();

const visible = computed(() => can('chats.view'));
const active = computed(() => isCurrentOrParentUrl('/chats'));
const unread = computed(() => {
    const value = (page.props as { chatUnreadCount?: number }).chatUnreadCount;
    return typeof value === 'number' ? value : 0;
});
</script>

<template>
    <Button
        v-if="visible"
        as-child
        type="button"
        variant="ghost"
        size="icon"
        class="relative size-9 rounded-full text-foreground hover:bg-[var(--brand-accent)]/20 hover:text-foreground"
        :class="active ? 'bg-[var(--brand-accent)]/15' : undefined"
        :aria-label="t('Chat')"
        :title="t('Chat')"
    >
        <Link href="/chats">
            <MessageCircle class="size-4 shrink-0" :stroke-width="1.75" />
            <span
                v-if="unread > 0"
                class="absolute end-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-destructive-foreground"
            >
                {{ unread > 99 ? '99+' : unread }}
            </span>
        </Link>
    </Button>
</template>
