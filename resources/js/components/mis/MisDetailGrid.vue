<script setup lang="ts">
import { cn } from '@/lib/utils';

export type MisDetailItem = {
    label: string;
    value?: string | number | null;
    span?: 1 | 2 | 3;
};

withDefaults(
    defineProps<{
        items?: MisDetailItem[];
        columns?: 1 | 2 | 3 | 4;
        class?: string;
    }>(),
    {
        items: () => [],
        columns: 3,
        class: undefined,
    },
);

const columnClass: Record<number, string> = {
    1: 'grid-cols-1',
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-2 lg:grid-cols-3',
    4: 'sm:grid-cols-2 lg:grid-cols-4',
};
</script>

<template>
    <div
        :class="
            cn('grid gap-3', columnClass[columns], $props.class)
        "
    >
        <slot>
            <div
                v-for="(item, index) in items"
                :key="`${item.label}-${index}`"
                :class="
                    cn(
                        'min-w-0',
                        item.span === 2 && 'sm:col-span-2',
                        item.span === 3 && 'lg:col-span-3',
                    )
                "
            >
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ item.label }}
                </p>
                <p class="mt-0.5 break-all">
                    {{
                        item.value === null ||
                        item.value === undefined ||
                        item.value === ''
                            ? '—'
                            : item.value
                    }}
                </p>
            </div>
        </slot>
    </div>
</template>
