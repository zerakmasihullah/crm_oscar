<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        label?: string;
        htmlFor?: string;
        hint?: string;
        error?: string;
        class?: string;
        required?: boolean;
    }>(),
    {
        label: undefined,
        htmlFor: undefined,
        hint: undefined,
        error: undefined,
        class: undefined,
        required: false,
    },
);
</script>

<template>
    <div :class="cn('grid gap-1.5', $props.class)">
        <Label
            v-if="label || $slots.label"
            :for="htmlFor"
            class="text-sm text-muted-foreground"
        >
            <slot name="label">
                {{ label }}
                <span v-if="required" class="text-destructive">*</span>
            </slot>
        </Label>
        <slot />
        <div class="min-h-5">
            <p v-if="error" class="text-xs text-destructive">
                {{ error }}
            </p>
            <p v-else-if="hint" class="text-xs text-muted-foreground">
                {{ hint }}
            </p>
        </div>
    </div>
</template>
