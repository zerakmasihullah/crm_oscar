<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    subtitle?: string;
    total: string;
    labels: string[];
    values: number[];
}>();

const points = computed(() => {
    const data = props.values.map((n) => Number(n) || 0);
    if (!data.length) return [];
    const width = 320;
    const height = 120;
    const padX = 12;
    const padY = 14;
    const max = Math.max(...data, 1);
    const min = Math.min(...data) * 0.85;
    const range = max - min || 1;

    return data.map((value, index) => {
        const x =
            padX + (index / Math.max(data.length - 1, 1)) * (width - padX * 2);
        const y = height - padY - ((value - min) / range) * (height - padY * 2);
        return { x, y, value };
    });
});

const line = computed(() => points.value.map((p) => `${p.x},${p.y}`).join(' '));

const area = computed(() => {
    if (!points.value.length) return '';
    const first = points.value[0]!;
    const last = points.value[points.value.length - 1]!;
    return `${first.x},118 ${line.value} ${last.x},118`;
});
</script>

<template>
    <section class="dash-card p-4">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-foreground">{{ title }}</p>
                <p v-if="subtitle" class="mt-0.5 text-xs text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>
            <p class="text-xl font-bold tabular-nums tracking-tight">
                {{ total }}
            </p>
        </div>

        <svg viewBox="0 0 320 128" class="h-36 w-full" aria-hidden="true">
            <defs>
                <linearGradient id="dash-area-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stop-color="var(--brand-accent)"
                        stop-opacity="0.28"
                    />
                    <stop
                        offset="100%"
                        stop-color="var(--brand-accent)"
                        stop-opacity="0.02"
                    />
                </linearGradient>
            </defs>
            <polygon :points="area" fill="url(#dash-area-fill)" />
            <polyline
                :points="line"
                fill="none"
                stroke="currentColor"
                class="text-foreground"
                stroke-width="2.25"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
    </section>
</template>

<style src="./dash-card.css"></style>
