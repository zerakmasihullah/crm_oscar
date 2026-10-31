<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        disabled?: boolean;
        onLabel?: string;
        offLabel?: string;
        class?: string;
    }>(),
    {
        disabled: false,
        onLabel: undefined,
        offLabel: undefined,
        class: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const { t } = useTranslations();

const onText = computed(() => props.onLabel ?? t('On'));
const offText = computed(() => props.offLabel ?? t('Off'));

function toggle(): void {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :disabled="disabled"
        :class="
            cn(
                'settings-switch relative inline-flex h-7 w-[3.85rem] shrink-0 items-center rounded-full border outline-none',
                'transition-[background-color,border-color,box-shadow] duration-300 ease-out',
                'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'active:scale-[0.97]',
                modelValue
                    ? 'settings-switch--on border-transparent bg-[var(--brand-accent)] text-[#111318] shadow-[inset_0_1px_0_rgba(255,255,255,0.35)]'
                    : 'settings-switch--off border-border/80 bg-muted text-muted-foreground shadow-[inset_0_1px_2px_rgba(15,23,42,0.06)] dark:border-white/12 dark:bg-white/[0.08] dark:shadow-[inset_0_1px_2px_rgba(0,0,0,0.35)]',
                props.class,
            )
        "
        @click="toggle"
    >
        <span
            class="pointer-events-none absolute inset-0 z-[1] grid grid-cols-2 items-center px-1.5 text-[9px] font-semibold tracking-wide uppercase"
            aria-hidden="true"
        >
            <span
                class="text-center transition-all duration-300 ease-out"
                :class="
                    modelValue
                        ? 'translate-x-0 text-[#111318] opacity-100'
                        : '-translate-x-0.5 text-[#111318] opacity-0'
                "
            >
                {{ onText }}
            </span>
            <span
                class="text-center transition-all duration-300 ease-out"
                :class="
                    modelValue
                        ? 'translate-x-0.5 text-muted-foreground opacity-0'
                        : 'translate-x-0 text-zinc-500 opacity-100 dark:text-zinc-300'
                "
            >
                {{ offText }}
            </span>
        </span>

        <span
            class="settings-switch-thumb pointer-events-none absolute top-0.5 start-0.5 z-[2] size-5 rounded-full"
            :class="modelValue ? 'is-on' : 'is-off'"
        />
    </button>
</template>
