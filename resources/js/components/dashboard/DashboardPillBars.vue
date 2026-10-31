<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    subtitle?: string;
    total: string;
    labels: string[];
    values: number[];
}>();

const max = computed(() =>
    Math.max(1, ...props.values.map((n) => Number(n) || 0)),
);

const peakIndex = computed(() => {
    let best = 0;
    props.values.forEach((value, index) => {
        if ((Number(value) || 0) > (Number(props.values[best]) || 0)) {
            best = index;
        }
    });
    return best;
});

const items = computed(() =>
    props.labels.map((label, index) => ({
        label,
        value: Number(props.values[index]) || 0,
        peak: index === peakIndex.value,
    })),
);
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

        <div class="flex h-36 items-end justify-between gap-2">
            <div
                v-for="item in items"
                :key="item.label"
                class="flex flex-1 flex-col items-center gap-1.5"
            >
                <div class="flex h-28 w-full items-end justify-center">
                    <div
                        class="w-full max-w-7 rounded-full transition-all"
                        :class="
                            item.peak
                                ? 'bg-[var(--brand-accent)]'
                                : 'bg-zinc-200 dark:bg-white/15'
                        "
                        :style="{
                            height: `${Math.max(12, (item.value / max) * 100)}%`,
                        }"
                    />
                </div>
                <span class="text-[11px] text-muted-foreground">
                    {{ item.label }}
                </span>
            </div>
        </div>
    </section>
</template>

<style src="./dash-card.css"></style>
