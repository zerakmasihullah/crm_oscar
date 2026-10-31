<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    subtitle?: string;
    labels: string[];
    income: number[];
    expenses: number[];
    incomeLabel?: string;
    expenseLabel?: string;
}>();

const uid = `fdl-${Math.random().toString(36).slice(2, 8)}`;

const layout = computed(() => {
    const income = props.income.map((n) => Number(n) || 0);
    const expenses = props.expenses.map((n) => Number(n) || 0);
    const n = Math.max(income.length, expenses.length, 1);
    const width = 560;
    const height = 200;
    const padX = 16;
    const padY = 20;
    const max = Math.max(1, ...income, ...expenses);
    const min = 0;
    const range = max - min || 1;

    const toPoints = (data: number[]) =>
        Array.from({ length: n }, (_, index) => {
            const value = data[index] ?? 0;
            const x =
                padX + (index / Math.max(n - 1, 1)) * (width - padX * 2);
            const y =
                height - padY - ((value - min) / range) * (height - padY * 2);
            return { x, y, value };
        });

    const incomePts = toPoints(income);
    const expensePts = toPoints(expenses);
    const line = (pts: { x: number; y: number }[]) =>
        pts.map((p) => `${p.x},${p.y}`).join(' ');
    const area = (pts: { x: number; y: number }[]) => {
        if (!pts.length) return '';
        const first = pts[0]!;
        const last = pts[pts.length - 1]!;
        return `${first.x},${height - 8} ${line(pts)} ${last.x},${height - 8}`;
    };

    const peak = incomePts.reduce(
        (best, p, index) => (p.value > best.value ? { ...p, index } : best),
        { x: 0, y: 0, value: 0, index: 0 },
    );

    return {
        width,
        height,
        incomePts,
        expensePts,
        incomeLine: line(incomePts),
        expenseLine: line(expensePts),
        incomeArea: area(incomePts),
        peak,
        labelStep: Math.max(1, Math.ceil(n / 6)),
    };
});
</script>

<template>
    <section class="dash-card p-4 sm:p-5">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-foreground">{{ title }}</p>
                <p v-if="subtitle" class="mt-0.5 text-xs text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-foreground" />
                    {{ incomeLabel || 'Income' }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span
                        class="size-2 rounded-full bg-[var(--brand-accent)]"
                    />
                    {{ expenseLabel || 'Expenses' }}
                </span>
            </div>
        </div>

        <svg
            :viewBox="`0 0 ${layout.width} ${layout.height}`"
            class="h-48 w-full sm:h-56"
            role="img"
            :aria-label="title"
        >
            <defs>
                <linearGradient :id="`${uid}-fill`" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stop-color="currentColor"
                        stop-opacity="0.12"
                    />
                    <stop
                        offset="100%"
                        stop-color="currentColor"
                        stop-opacity="0.01"
                    />
                </linearGradient>
            </defs>

            <polygon
                :points="layout.incomeArea"
                class="text-foreground"
                :fill="`url(#${uid}-fill)`"
            />
            <polyline
                :points="layout.incomeLine"
                fill="none"
                stroke="currentColor"
                class="text-foreground"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <polyline
                :points="layout.expenseLine"
                fill="none"
                stroke="var(--brand-accent)"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <g v-if="layout.peak.value > 0">
                <line
                    :x1="layout.peak.x"
                    y1="12"
                    :x2="layout.peak.x"
                    :y2="layout.height - 12"
                    stroke="currentColor"
                    class="text-foreground/15"
                    stroke-dasharray="3 4"
                />
                <circle
                    :cx="layout.peak.x"
                    :cy="layout.peak.y"
                    r="4.5"
                    class="fill-foreground"
                />
                <rect
                    :x="Math.min(layout.peak.x - 36, layout.width - 84)"
                    :y="Math.max(8, layout.peak.y - 28)"
                    width="72"
                    height="20"
                    rx="6"
                    class="fill-foreground"
                />
                <text
                    :x="Math.min(layout.peak.x, layout.width - 48)"
                    :y="Math.max(22, layout.peak.y - 14)"
                    text-anchor="middle"
                    class="fill-background text-[10px] font-semibold"
                >
                    €{{ layout.peak.value.toFixed(0) }}
                </text>
            </g>
        </svg>

        <div
            class="mt-1 flex justify-between gap-1 overflow-hidden px-1 text-[10px] text-muted-foreground"
        >
            <span
                v-for="(label, index) in labels"
                v-show="index % layout.labelStep === 0"
                :key="`${label}-${index}`"
            >
                {{ label }}
            </span>
        </div>
    </section>
</template>

<style src="@/components/dashboard/dash-card.css"></style>
