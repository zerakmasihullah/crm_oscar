<script setup lang="ts">
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        label?: string;
        class?: string;
    }>(),
    {
        label: 'Loading…',
        class: undefined,
    },
);
</script>

<template>
    <div
        role="status"
        :aria-busy="true"
        :class="
            cn(
                'flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border/80 bg-muted/20 px-6 py-14 text-center',
                $props.class,
            )
        "
    >
        <Spinner class="size-6 text-muted-foreground" />
        <p class="text-sm text-muted-foreground">
            <slot>{{ label }}</slot>
        </p>
    </div>
</template>
