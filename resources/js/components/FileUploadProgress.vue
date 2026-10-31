<script setup lang="ts">
import { X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { ChunkedUploadItem } from '@/composables/useChunkedUpload';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    items: ChunkedUploadItem[];
}>();

const emit = defineEmits<{
    cancel: [id: string];
    dismiss: [id: string];
    clearFinished: [];
}>();

const { t } = useTranslations();

function formatBytes(bytes: number | null | undefined): string {
    if (bytes == null) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

function formatSpeed(bps: number): string {
    if (!bps || bps < 1) {
        return '';
    }

    return `${formatBytes(Math.round(bps))}/s`;
}

function statusLabel(status: ChunkedUploadItem['status']): string {
    switch (status) {
        case 'queued':
            return t('Queued');
        case 'hashing':
            return t('Preparing…');
        case 'uploading':
            return t('Uploading…');
        case 'assembling':
            return t('Finalizing…');
        case 'completed':
            return t('Completed');
        case 'failed':
            return t('Failed');
        case 'aborted':
            return t('Cancelled');
        case 'paused':
            return t('Paused');
        default:
            return status;
    }
}

const hasFinished = () =>
    props.items.some((item) => ['completed', 'failed', 'aborted'].includes(item.status));
</script>

<template>
    <div v-if="items.length > 0" class="space-y-3 rounded-xl border border-border/70 bg-muted/20 p-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold">{{ t('Uploads') }}</p>
            <Button
                v-if="hasFinished()"
                type="button"
                variant="ghost"
                size="sm"
                @click="emit('clearFinished')"
            >
                {{ t('Clear finished') }}
            </Button>
        </div>

        <div
            v-for="item in items"
            :key="item.id"
            class="rounded-lg border border-border/60 bg-card px-3 py-3"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ item.file.name }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ statusLabel(item.status) }}
                        · {{ formatBytes(item.loadedBytes) }} / {{ formatBytes(item.totalBytes) }}
                        <span v-if="item.speedBps > 0 && item.status === 'uploading'">
                            · {{ formatSpeed(item.speedBps) }}
                        </span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        v-if="['queued', 'hashing', 'uploading', 'assembling'].includes(item.status)"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="emit('cancel', item.id)"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button
                        v-else
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        @click="emit('dismiss', item.id)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </div>

            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="
                        item.status === 'failed' || item.status === 'aborted'
                            ? 'bg-destructive'
                            : item.status === 'completed'
                              ? 'bg-[var(--brand-accent)]'
                              : 'bg-primary'
                    "
                    :style="{ width: `${Math.min(100, Math.max(0, item.progress))}%` }"
                />
            </div>

            <p v-if="item.error" class="mt-2 text-xs text-destructive">
                {{ item.error }}
            </p>
        </div>
    </div>
</template>
