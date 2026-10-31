<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { cn } from '@/lib/utils';

const open = defineModel<boolean>('open', { default: false });

withDefaults(
    defineProps<{
        class?: string;
    }>(),
    {
        class: undefined,
    },
);

function toggle(): void {
    open.value = !open.value;
}
</script>

<template>
    <div
        :class="
            cn(
                'overflow-hidden rounded-2xl border border-border/80 bg-card shadow-soft transition-[box-shadow] duration-300 dark:border-white/10',
                open && 'shadow-soft-lg',
                $props.class,
            )
        "
    >
        <button
            type="button"
            class="flex w-full items-start gap-3 px-4 py-3.5 text-start transition-colors duration-200 hover:bg-muted/30"
            :aria-expanded="open"
            @click="toggle"
        >
            <div class="min-w-0 flex-1">
                <slot />
            </div>
            <ChevronDown
                class="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform duration-300 ease-[cubic-bezier(0.34,1.4,0.64,1)]"
                :class="open ? 'rotate-180' : 'rotate-0'"
            />
        </button>
        <div
            class="grid transition-[grid-template-rows] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
            :class="open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
        >
            <div class="min-h-0 overflow-hidden">
                <div
                    class="border-t border-border/70 bg-muted/20 px-4 py-3 transition-opacity duration-300"
                    :class="open ? 'opacity-100' : 'opacity-0'"
                >
                    <slot name="detail" />
                </div>
            </div>
        </div>
    </div>
</template>
