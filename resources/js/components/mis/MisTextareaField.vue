<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, useId } from 'vue';
import MisField from '@/components/mis/MisField.vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const model = defineModel<string | null | undefined>({ default: '' });

const props = withDefaults(
    defineProps<{
        id?: string;
        label?: string;
        hint?: string;
        error?: string;
        placeholder?: string;
        disabled?: boolean;
        required?: boolean;
        rows?: number;
        class?: HTMLAttributes['class'];
        textareaClass?: HTMLAttributes['class'];
    }>(),
    {
        id: undefined,
        label: undefined,
        hint: undefined,
        error: undefined,
        placeholder: undefined,
        disabled: false,
        required: false,
        rows: 4,
        class: undefined,
        textareaClass: undefined,
    },
);

const autoId = useId();
const fieldId = computed(() => props.id || autoId);
</script>

<template>
    <MisField
        :label="label"
        :html-for="fieldId"
        :hint="hint"
        :error="error"
        :required="required"
        :class="props.class"
    >
        <textarea
            :id="fieldId"
            v-model="model"
            v-bind="$attrs"
            :rows="rows"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :aria-invalid="error ? true : undefined"
            :class="
                cn(
                    'border-input dark:bg-muted/70 placeholder:text-muted-foreground flex min-h-28 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                    textareaClass,
                )
            "
        />
    </MisField>
</template>
