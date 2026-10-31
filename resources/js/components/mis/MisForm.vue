<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, useSlots } from 'vue';
import { Link } from '@inertiajs/vue3';
import MisFormErrors, {
    type MisFormErrorBag,
} from '@/components/mis/MisFormErrors.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        errors?: MisFormErrorBag;
        /** When true, also render a top-of-form error summary. Field errors stay on each input. */
        showErrorSummary?: boolean;
        processing?: boolean;
        submitLabel?: string;
        cancelHref?: string;
        cancelLabel?: string;
        showActions?: boolean;
        class?: HTMLAttributes['class'];
        fieldsClass?: HTMLAttributes['class'];
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';
    }>(),
    {
        title: undefined,
        description: undefined,
        errors: undefined,
        showErrorSummary: false,
        processing: false,
        submitLabel: undefined,
        cancelHref: undefined,
        cancelLabel: undefined,
        showActions: true,
        class: undefined,
        fieldsClass: undefined,
        maxWidth: '2xl',
    },
);

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useTranslations();
const slots = useSlots();

const maxWidthClass = computed(() => {
    const map = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-3xl',
        '2xl': 'max-w-5xl',
        full: 'max-w-none',
    } as const;
    return map[props.maxWidth];
});
</script>

<template>
    <form
        :class="cn('mx-auto flex w-full flex-col gap-6', maxWidthClass, props.class)"
        @submit.prevent="emit('submit')"
    >
        <div
            v-if="title || description || slots.header || slots.description"
            class="space-y-1.5"
        >
            <slot name="header">
                <h1 v-if="title" class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
            </slot>
            <p
                v-if="description || slots.description"
                class="text-sm text-muted-foreground"
            >
                <slot name="description">{{ description }}</slot>
            </p>
        </div>

        <MisFormErrors v-if="showErrorSummary" :errors="errors" />

        <div :class="cn('grid gap-5', fieldsClass)">
            <slot />
        </div>

        <div
            v-if="showActions || slots.actions"
            class="flex flex-wrap items-center justify-end gap-2 border-t border-border/50 pt-5"
        >
            <slot name="actions">
                <Button
                    v-if="cancelHref"
                    as-child
                    type="button"
                    variant="outline"
                    size="lg"
                    :disabled="processing"
                >
                    <Link :href="cancelHref">
                        {{ cancelLabel || t('Cancel') }}
                    </Link>
                </Button>
                <Button type="submit" size="lg" :disabled="processing">
                    {{ submitLabel || t('Save') }}
                </Button>
            </slot>
        </div>

        <slot v-if="slots.footer" name="footer" />
    </form>
</template>
