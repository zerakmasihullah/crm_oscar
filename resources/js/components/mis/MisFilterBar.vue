<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        applyLabel?: string;
        clearLabel?: string;
        showActions?: boolean;
        /** `card` = bordered panel; `plain` = no chrome (inline toolbar). */
        variant?: 'card' | 'plain';
        class?: string;
    }>(),
    {
        applyLabel: 'Apply',
        clearLabel: 'Clear',
        showActions: true,
        variant: 'card',
        class: undefined,
    },
);

const emit = defineEmits<{
    apply: [];
    clear: [];
}>();
</script>

<template>
    <form
        :class="
            cn(
                variant === 'card' &&
                    'rounded-2xl border border-border/80 bg-card p-4 shadow-soft dark:border-white/10',
                variant === 'plain' && 'space-y-3',
                $props.class,
            )
        "
        @submit.prevent="emit('apply')"
    >
        <div class="flex flex-wrap items-end gap-3">
            <slot />
            <div
                v-if="showActions || $slots.actions"
                class="flex flex-wrap items-center gap-2 pb-px"
            >
                <slot name="actions">
                    <Button type="submit" size="sm">
                        {{ applyLabel }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emit('clear')"
                    >
                        {{ clearLabel }}
                    </Button>
                </slot>
            </div>
        </div>
        <div v-if="$slots.advanced" class="mt-3">
            <slot name="advanced" />
        </div>
    </form>
</template>
