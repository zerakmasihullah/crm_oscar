<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CloudOff, RefreshCw, Wifi } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { useOfflineSync } from '@/offline/useOfflineForm';

const props = defineProps<{
    enabled: boolean;
}>();

const page = usePage();
const { t } = useTranslations();

const autoSync = computed(() => {
    const settings = page.props.offlineSettings as { auto_sync?: boolean } | undefined;

    return settings?.auto_sync !== false;
});

const { online, pending, flush } = useOfflineSync(props.enabled, true);

const visible = computed(() => props.enabled && (! online.value || pending.value > 0));
</script>

<template>
    <div
        v-if="visible"
        class="flex items-center justify-between gap-3 border-b px-4 py-2 text-sm"
        :class="online ? 'border-amber-500/30 bg-amber-500/10 text-amber-950 dark:text-amber-100' : 'border-red-500/30 bg-red-500/10 text-red-950 dark:text-red-100'"
        role="status"
    >
        <div class="flex items-center gap-2">
            <CloudOff v-if="!online" class="size-4 shrink-0" />
            <Wifi v-else class="size-4 shrink-0" />
            <span v-if="!online">
                {{ t('You are offline. Changes will sync when you reconnect.') }}
            </span>
            <span v-else>
                {{ t(':count pending offline submission(s)', { count: String(pending) }) }}
            </span>
            <span v-if="!autoSync" class="text-xs opacity-80">
                ({{ t('Auto-sync off') }})
            </span>
        </div>
        <Button
            v-if="online && pending > 0"
            type="button"
            size="sm"
            variant="outline"
            class="h-7 gap-1"
            @click="flush"
        >
            <RefreshCw class="size-3.5" />
            {{ t('Sync now') }}
        </Button>
    </div>
</template>
