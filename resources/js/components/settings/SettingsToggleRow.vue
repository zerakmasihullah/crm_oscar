<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import SettingsSwitch from '@/components/settings/SettingsSwitch.vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string | null;
        modelValue: boolean;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        description: null,
        disabled: false,
        class: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'flex items-start justify-between gap-5 py-5 first:pt-0 last:pb-0',
                props.class,
            )
        "
    >
        <div class="min-w-0 flex-1 space-y-1 pr-2">
            <h3 class="text-sm font-semibold tracking-tight text-foreground">
                {{ title }}
            </h3>
            <p
                v-if="description || $slots.description"
                class="max-w-xl text-[13px] leading-relaxed text-muted-foreground"
            >
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <SettingsSwitch
            class="mt-0.5"
            :model-value="modelValue"
            :disabled="disabled"
            @update:model-value="(value) => emit('update:modelValue', value)"
        />
    </div>
</template>
