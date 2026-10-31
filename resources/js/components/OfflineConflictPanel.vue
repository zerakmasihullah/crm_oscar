<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { ConflictRecord } from '@/offline/db';
import {
    listConflicts,
    resolveConflictKeepLocal,
    resolveConflictUseServer,
} from '@/offline/outbox';
import { coordinatedFlush } from '@/offline/register';

const { t } = useTranslations();
const conflicts = ref<ConflictRecord[]>([]);
const resolving = ref<number | null>(null);

async function refresh() {
    conflicts.value = await listConflicts();
}

async function keepLocal(id?: number) {
    if (! id) {
        return;
    }

    resolving.value = id;

    try {
        await resolveConflictKeepLocal(id);
        await coordinatedFlush();
        await refresh();
    } finally {
        resolving.value = null;
    }
}

async function useServer(id?: number) {
    if (! id) {
        return;
    }

    resolving.value = id;

    try {
        await resolveConflictUseServer(id);
        await refresh();
    } finally {
        resolving.value = null;
    }
}

onMounted(() => {
    void refresh();
});
</script>

<template>
    <div v-if="conflicts.length" class="space-y-3 rounded-md border border-amber-500/40 bg-amber-500/5 p-4">
        <h2 class="text-sm font-semibold">{{ t('Sync conflicts') }}</h2>
        <p class="text-sm text-muted-foreground">
            {{ t('Server data was newer. Choose which version to keep.') }}
        </p>
        <ul class="space-y-2">
            <li
                v-for="conflict in conflicts"
                :key="conflict.id"
                class="flex flex-col gap-3 rounded border bg-background px-3 py-3 text-sm sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0 space-y-1">
                    <div class="font-medium">{{ conflict.resource }} · {{ conflict.client_uuid }}</div>
                    <div class="text-muted-foreground">{{ conflict.message }}</div>
                    <div class="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                        <div class="rounded border px-2 py-1">
                            <div class="font-medium text-foreground">{{ t('Your version') }}</div>
                            <pre class="mt-1 max-h-24 overflow-auto whitespace-pre-wrap">{{
                                JSON.stringify(conflict.local, null, 2)
                            }}</pre>
                        </div>
                        <div class="rounded border px-2 py-1">
                            <div class="font-medium text-foreground">{{ t('Server version') }}</div>
                            <pre class="mt-1 max-h-24 overflow-auto whitespace-pre-wrap">{{
                                JSON.stringify(conflict.server, null, 2)
                            }}</pre>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :disabled="resolving === conflict.id"
                        @click="keepLocal(conflict.id)"
                    >
                        {{ t('Keep my version') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="resolving === conflict.id"
                        @click="useServer(conflict.id)"
                    >
                        {{ t('Use server version') }}
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
