<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, useAttrs } from 'vue';
import { useVModel } from '@vueuse/core';
import { DatePicker } from '@/components/ui/date-picker';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
    type?: string;
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const attrs = useAttrs();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

// Only the explicit `type` prop — never attrs — so other fields cannot
// accidentally render as the date picker (e.g. AI "model" / password fields).
const isDateInput = computed(() => props.type === 'date');

const dateValue = computed({
    get: () => (modelValue.value == null ? '' : String(modelValue.value)),
    set: (value: string) => {
        modelValue.value = value;
    },
});
</script>

<template>
    <DatePicker
        v-if="isDateInput"
        v-model="dateValue"
        :id="(attrs.id as string | undefined)"
        :disabled="Boolean(attrs.disabled)"
        :required="Boolean(attrs.required)"
        :min="(attrs.min as string | undefined) ?? null"
        :max="(attrs.max as string | undefined) ?? null"
        :placeholder="(attrs.placeholder as string | undefined) ?? ''"
        :class="typeof props.class === 'string' ? props.class : undefined"
    />
    <input
        v-else
        v-model="modelValue"
        data-slot="input"
        v-bind="attrs"
        :type="type ?? 'text'"
        :class="
            cn(
                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-muted/70 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                props.class,
            )
        "
    />
</template>
