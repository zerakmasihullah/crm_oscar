<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, useId } from 'vue';
import MisField from '@/components/mis/MisField.vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const model = defineModel<string | number | null | undefined>({ default: '' });

const props = withDefaults(
    defineProps<{
        id?: string;
        label?: string;
        hint?: string;
        error?: string;
        type?: string;
        placeholder?: string;
        disabled?: boolean;
        required?: boolean;
        autocomplete?: string;
        prefix?: string;
        suffix?: string;
        class?: HTMLAttributes['class'];
        inputClass?: HTMLAttributes['class'];
    }>(),
    {
        id: undefined,
        label: undefined,
        hint: undefined,
        error: undefined,
        type: 'text',
        placeholder: undefined,
        disabled: false,
        required: false,
        autocomplete: undefined,
        prefix: undefined,
        suffix: undefined,
        class: undefined,
        inputClass: undefined,
    },
);

const autoId = useId();
const fieldId = computed(() => props.id || autoId);
const hasAdornment = computed(() => Boolean(props.prefix || props.suffix));
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
        <div
            :class="
                cn(
                    hasAdornment &&
                        'flex items-stretch overflow-hidden rounded-xl border border-input bg-background shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    error &&
                        hasAdornment &&
                        'border-destructive focus-within:border-destructive focus-within:ring-destructive/20',
                )
            "
        >
            <span
                v-if="prefix"
                class="flex items-center border-e border-border/70 bg-muted/40 px-3 text-sm text-muted-foreground"
            >
                {{ prefix }}
            </span>
            <Input
                :id="fieldId"
                v-model="model"
                v-bind="$attrs"
                :type="type"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                :autocomplete="autocomplete"
                :aria-invalid="error ? true : undefined"
                :class="
                    cn(
                        hasAdornment &&
                            'rounded-none border-0 bg-transparent shadow-none focus-visible:ring-0',
                        inputClass,
                    )
                "
            />
            <span
                v-if="suffix"
                class="flex items-center border-s border-border/70 bg-muted/40 px-3 text-sm text-muted-foreground"
            >
                {{ suffix }}
            </span>
        </div>
    </MisField>
</template>
