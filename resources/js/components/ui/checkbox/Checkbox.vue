<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { Check } from '@lucide/vue';
import { reactiveOmit } from '@vueuse/core';
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type CheckedState = boolean | 'indeterminate';

const props = defineProps<
    CheckboxRootProps & {
        class?: HTMLAttributes['class'];
        /** @deprecated Prefer modelValue — kept for shadcn-style call sites. */
        checked?: CheckedState;
    }
>();

const emits = defineEmits<
    CheckboxRootEmits & {
        'update:checked': [value: CheckedState];
    }
>();

const delegatedProps = reactiveOmit(props, 'class', 'checked', 'modelValue');

const value = computed<CheckedState | undefined>(() => {
    if (props.modelValue !== undefined) {
        return props.modelValue as CheckedState;
    }

    return props.checked;
});

function onUpdate(next: CheckedState): void {
    emits('update:modelValue', next);
    emits('update:checked', next);
}
</script>

<template>
    <CheckboxRoot
        v-slot="slotProps"
        data-slot="checkbox"
        v-bind="delegatedProps"
        :model-value="value"
        :class="
            cn(
                'peer border-input bg-background data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary dark:border-white/20 dark:bg-white/[0.04] dark:data-[state=checked]:bg-[var(--brand-accent)] dark:data-[state=checked]:border-[var(--brand-accent)] dark:data-[state=checked]:text-[#111318] focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
        @update:model-value="onUpdate"
    >
        <CheckboxIndicator
            data-slot="checkbox-indicator"
            class="grid place-content-center text-current transition-none"
        >
            <slot v-bind="slotProps">
                <Check class="size-3.5" />
            </slot>
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
