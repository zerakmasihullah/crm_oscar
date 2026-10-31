<script setup lang="ts">
import { computed } from 'vue';

type Item = { label: string; value: number; meta?: string };

const props = defineProps<{
    title: string;
    subtitle?: string;
    items: Item[];
    accentIndex?: number;
}>();

const total = computed(() =>
    props.items.reduce((sum, item) => sum + (Number(item.value) || 0), 0),
);

const rows = computed(() => {
    const sum = total.value || 1;
    return props.items.map((item, index) => {
        const value = Number(item.value) || 0;
        const pct = Math.round((value / sum) * 100);
        return {
            ...item,
            value,
            pct,
            accent: index === (props.accentIndex ?? 0),
        };
    });
});

function money(value: number): string {
    return `€${value.toFixed(2)}`;
}
</script>

<template>
    <section class="dash-card flex h-full flex-col p-4 sm:p-5">
        <div class="mb-4 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-foreground">{{ title }}</p>
                <p v-if="subtitle" class="mt-0.5 text-xs text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>
            <p class="text-lg font-bold tabular-nums tracking-tight">
                {{ money(total) }}
            </p>
        </div>

        <div class="space-y-3.5">
            <div v-for="row in rows" :key="row.label">
                <div class="mb-1.5 flex items-center justify-between gap-2 text-xs">
                    <span class="min-w-0 truncate font-medium text-foreground/90">
                        {{ row.label }}
                    </span>
                    <span class="shrink-0 tabular-nums text-muted-foreground">
                        {{ money(row.value) }}
                        <span class="text-foreground/40">·</span>
                        {{ row.pct }}%
                    </span>
                </div>
                <div class="dash-track h-2.5 overflow-hidden rounded-full">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="
                            row.accent
                                ? 'bg-[var(--brand-accent)]'
                                : 'bg-foreground/70 dark:bg-white/35'
                        "
                        :style="{
                            width: `${Math.max(row.pct, row.value ? 3 : 0)}%`,
                        }"
                    />
                </div>
                <p
                    v-if="row.meta"
                    class="mt-1 text-[11px] text-muted-foreground"
                >
                    {{ row.meta }}
                </p>
            </div>
            <p
                v-if="!rows.length"
                class="py-8 text-center text-sm text-muted-foreground"
            >
                —
            </p>
        </div>
    </section>
</template>

<style src="@/components/dashboard/dash-card.css"></style>
