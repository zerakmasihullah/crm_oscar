<script setup lang="ts">
import { computed } from 'vue';

type Item = { label: string; value: number; color?: string };

const props = defineProps<{
    title: string;
    subtitle?: string;
    items: Item[];
    centerLabel?: string;
}>();

const palette = [
    'var(--brand-accent)',
    '#18181b',
    '#fb923c',
    '#a1a1aa',
    '#fbbf24',
    '#71717a',
];

const total = computed(() =>
    props.items.reduce((sum, item) => sum + Math.max(0, Number(item.value) || 0), 0),
);

const slices = computed(() => {
    const sum = total.value || 1;
    let angle = -Math.PI / 2;
    const cx = 80;
    const cy = 80;
    const r = 58;
    const inner = 36;

    return props.items
        .map((item, index) => {
            const value = Math.max(0, Number(item.value) || 0);
            if (value <= 0) return null;
            const slice = (value / sum) * Math.PI * 2;
            const start = angle;
            const end = angle + Math.max(slice, 0.0001);
            angle = end;
            const x1 = cx + r * Math.cos(start);
            const y1 = cy + r * Math.sin(start);
            const x2 = cx + r * Math.cos(end);
            const y2 = cy + r * Math.sin(end);
            const ix1 = cx + inner * Math.cos(end);
            const iy1 = cy + inner * Math.sin(end);
            const ix2 = cx + inner * Math.cos(start);
            const iy2 = cy + inner * Math.sin(start);
            const large = slice > Math.PI ? 1 : 0;

            return {
                label: item.label,
                value,
                pct: Math.round((value / sum) * 100),
                color: item.color ?? palette[index % palette.length],
                d: `M ${x1} ${y1} A ${r} ${r} 0 ${large} 1 ${x2} ${y2} L ${ix1} ${iy1} A ${inner} ${inner} 0 ${large} 0 ${ix2} ${iy2} Z`,
            };
        })
        .filter(Boolean) as Array<{
        label: string;
        value: number;
        pct: number;
        color: string;
        d: string;
    }>;
});
</script>

<template>
    <section class="dash-card flex h-full flex-col p-4 sm:p-5">
        <div class="mb-3">
            <p class="text-sm font-semibold text-foreground">{{ title }}</p>
            <p v-if="subtitle" class="mt-0.5 text-xs text-muted-foreground">
                {{ subtitle }}
            </p>
        </div>

        <div
            v-if="slices.length"
            class="flex flex-1 flex-col items-center gap-4 sm:flex-row sm:items-center"
        >
            <div class="relative shrink-0">
                <svg viewBox="0 0 160 160" class="size-36 sm:size-40">
                    <path
                        v-for="slice in slices"
                        :key="slice.label"
                        :d="slice.d"
                        :fill="slice.color"
                    />
                </svg>
                <div
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                >
                    <p class="text-[10px] text-muted-foreground">
                        {{ centerLabel || 'Total' }}
                    </p>
                    <p class="text-sm font-bold tabular-nums">
                        €{{ total.toFixed(0) }}
                    </p>
                </div>
            </div>

            <ul class="w-full min-w-0 flex-1 space-y-2.5">
                <li
                    v-for="slice in slices"
                    :key="slice.label"
                    class="flex items-center gap-2"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ background: slice.color }"
                    />
                    <span class="min-w-0 flex-1 truncate text-xs text-foreground/85">
                        {{ slice.label }}
                    </span>
                    <span class="shrink-0 text-xs font-semibold tabular-nums">
                        {{ slice.pct }}%
                    </span>
                </li>
            </ul>
        </div>
        <p
            v-else
            class="flex flex-1 items-center justify-center py-10 text-sm text-muted-foreground"
        >
            —
        </p>
    </section>
</template>

<style src="@/components/dashboard/dash-card.css"></style>
