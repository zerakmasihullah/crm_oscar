<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import LocaleSync from '@/components/LocaleSync.vue';
import OfflineBanner from '@/components/OfflineBanner.vue';
import OfflineConflictPanel from '@/components/OfflineConflictPanel.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { useLocale } from '@/composables/useLocale';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const offlineEnabled = computed(() =>
    Boolean((page.props as { offlineEnabled?: boolean }).offlineEnabled),
);
const { dir } = useLocale();
const appName = computed(() => (page.props.name as string) || 'Oscar');

const toasterPosition = computed(() =>
    dir.value === 'rtl' ? 'top-left' : 'top-right',
);
const sidebarOpen = computed(() => Boolean(page.props.sidebarOpen));
const flushContent = computed(() => {
    const url = String(page.url ?? '');
    return url.startsWith('/ai/analytics') || url.startsWith('/chats');
});
</script>

<template>
    <LocaleSync>
        <SidebarProvider :default-open="sidebarOpen">
            <div class="orvion-shell relative flex h-svh w-full overflow-hidden">
                <div
                    class="orvion-shell-bg pointer-events-none absolute inset-0"
                    aria-hidden="true"
                >
                    <span class="orvion-watermark">{{ appName }}</span>
                    <span class="orvion-blob orvion-blob-a" />
                    <span class="orvion-blob orvion-blob-b" />
                </div>

                <AppSidebar />

                <div class="relative z-10 flex min-h-0 min-w-0 flex-1 flex-col">
                    <OfflineBanner :enabled="offlineEnabled" />
                    <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                    <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
                        <div
                            class="flex min-h-0 flex-1 flex-col"
                            :class="
                                flushContent
                                    ? 'overflow-hidden'
                                    : 'space-y-6 overflow-y-auto px-5 pt-10 pb-12 md:px-8 md:pt-12'
                            "
                        >
                            <OfflineConflictPanel v-if="offlineEnabled" />
                            <slot />
                        </div>
                    </main>
                </div>
            </div>
            <Toaster :position="toasterPosition" />
        </SidebarProvider>
    </LocaleSync>
</template>
