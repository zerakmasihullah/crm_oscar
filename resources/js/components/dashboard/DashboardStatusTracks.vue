<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    subtitle?: string;
    items: { label: string; value: number }[];
}>();

const total = computed(() =>
    props.items.reduce((sum, item) => sum + (Number(item.value) || 0), 0),
);

const rows = computed(() => {
    const sum = total.value || 1;
    return props.items.map((item, index) => {
        const value = Number(item.value) || 0;
        return {
            label: item.label,
            value,
            pct: Math.round((value / sum) * 100),
            accent: index === 0,
        };
    });
});
</script>

<template>
    <section class="dash-card p-4">
        <div class="mb-4 flex items-end justify-between gap-3">
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

        <div class="space-y-3">
            <div v-for="row in rows" :key="row.label">
                <div class="mb-1 flex items-center justify-between text-xs">
                    <span class="text-foreground/85">{{ row.label }}</span>
                    <span class="tabular-nums text-muted-foreground">
                        {{ row.value }} · {{ row.pct }}%
                    </span>
                </div>
                <div class="dash-track h-2 overflow-hidden rounded-full">
                    <div
                        class="h-full rounded-full"
                        :class="
                            row.accent
                                ? 'bg-[var(--brand-accent)]'
                                : 'bg-foreground/60 dark:bg-white/30'
                        "
                        :style="{
                            width: `${Math.max(row.pct, row.value ? 4 : 0)}%`,
                        }"
                    />
                </div>
            </div>
            <p v-if="!rows.length" class="text-sm text-muted-foreground">—</p>
        </div>
    </section>
</template>

<style src="./dash-card.css"></style>
