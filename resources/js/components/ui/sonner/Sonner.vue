<script lang="ts" setup>
import type { ToasterProps } from 'vue-sonner';
import {
    CircleCheckIcon,
    InfoIcon,
    Loader2Icon,
    OctagonXIcon,
    TriangleAlertIcon,
    XIcon,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';
import { Toaster as Sonner } from 'vue-sonner';
import { installToastShatter } from '@/lib/toastShatter';
import { cn } from '@/lib/utils';

import 'vue-sonner/style.css';

const props = withDefaults(defineProps<ToasterProps>(), {
    closeButton: true,
    duration: 4200,
    richColors: true,
    expand: true,
    gap: 12,
    visibleToasts: 4,
});

const closeButtonPosition = computed(() =>
    String(props.position ?? 'top-right').includes('left')
        ? 'top-left'
        : 'top-right',
);

let stopShatter: (() => void) | undefined;

onMounted(() => {
    stopShatter = installToastShatter();
});

onUnmounted(() => {
    stopShatter?.();
});
</script>

<template>
    <Sonner
        :class="cn('kit-toaster group', props.class)"
        close-button
        :close-button-position="closeButtonPosition"
        :duration="props.duration"
        :rich-colors="props.richColors"
        :expand="props.expand"
        :gap="props.gap"
        :visible-toasts="props.visibleToasts"
        :position="props.position"
        :theme="props.theme"
        :dir="props.dir"
        :offset="props.offset"
        :style="{
            '--normal-bg':
                'color-mix(in oklab, var(--popover) 90%, transparent)',
            '--normal-text': 'var(--popover-foreground)',
            '--normal-border':
                'color-mix(in oklab, var(--border) 75%, transparent)',
            '--success-bg':
                'color-mix(in oklab, var(--popover) 88%, var(--brand-accent))',
            '--success-border':
                'color-mix(in oklab, var(--border) 50%, var(--brand-accent))',
            '--success-text': 'var(--popover-foreground)',
            '--error-bg':
                'color-mix(in oklab, var(--popover) 88%, oklch(0.94 0.04 25))',
            '--error-border':
                'color-mix(in oklab, var(--border) 50%, oklch(0.7 0.13 25))',
            '--error-text': 'var(--popover-foreground)',
            '--border-radius': '1.25rem',
            '--width': '372px',
        }"
        :toast-options="{
            class: 'kit-toast',
            ...props.toastOptions,
        }"
    >
        <template #success-icon>
            <span class="kit-toast-icon kit-toast-icon--success">
                <CircleCheckIcon class="size-4" />
            </span>
        </template>
        <template #info-icon>
            <span class="kit-toast-icon kit-toast-icon--info">
                <InfoIcon class="size-4" />
            </span>
        </template>
        <template #warning-icon>
            <span class="kit-toast-icon kit-toast-icon--warning">
                <TriangleAlertIcon class="size-4" />
            </span>
        </template>
        <template #error-icon>
            <span class="kit-toast-icon kit-toast-icon--error">
                <OctagonXIcon class="size-4" />
            </span>
        </template>
        <template #loading-icon>
            <span class="kit-toast-icon kit-toast-icon--info">
                <Loader2Icon class="size-4 animate-spin" />
            </span>
        </template>
        <template #close-icon>
            <XIcon class="size-3.5" />
        </template>
    </Sonner>
</template>
