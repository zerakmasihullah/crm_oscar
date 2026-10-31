<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import OfflineBanner from '@/components/OfflineBanner.vue';
import OfflineConflictPanel from '@/components/OfflineConflictPanel.vue';
import { Toaster } from '@/components/ui/sonner';
import { useLocale } from '@/composables/useLocale';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const offlineEnabled = computed(() =>
    Boolean((page.props as { offlineEnabled?: boolean }).offlineEnabled),
);
const { dir } = useLocale();

const toasterPosition = computed(() =>
    dir.value === 'rtl' ? 'top-left' : 'top-right',
);

const flushContent = computed(() =>
    String(page.url ?? '').startsWith('/chats'),
);
</script>

<template>
    <AppShell variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header">
            <OfflineBanner :enabled="offlineEnabled" />
            <div
                class="flex w-full flex-1 flex-col"
                :class="
                    flushContent
                        ? 'min-h-0 overflow-hidden'
                        : 'mx-auto max-w-[90rem] space-y-6 px-4 py-6 md:px-6 md:pb-12'
                "
            >
                <OfflineConflictPanel v-if="offlineEnabled" />
                <slot />
            </div>
        </AppContent>
        <Toaster :position="toasterPosition" />
    </AppShell>
</template>
