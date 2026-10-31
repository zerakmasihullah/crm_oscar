<script setup lang="ts">
import { computed } from 'vue';
import MisAlert from '@/components/mis/MisAlert.vue';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

export type MisFormErrorBag =
    | Record<string, string | string[] | undefined | null>
    | string[]
    | string
    | null
    | undefined;

const props = withDefaults(
    defineProps<{
        errors?: MisFormErrorBag;
        title?: string;
        class?: string;
    }>(),
    {
        errors: undefined,
        title: undefined,
        class: undefined,
    },
);

const { t } = useTranslations();

const messages = computed(() => {
    const bag = props.errors;
    if (!bag) {
        return [] as string[];
    }
    if (typeof bag === 'string') {
        return bag.trim() ? [bag] : [];
    }
    if (Array.isArray(bag)) {
        return bag.map(String).filter((message) => message.trim() !== '');
    }

    const out: string[] = [];
    for (const value of Object.values(bag)) {
        if (value == null || value === '') {
            continue;
        }
        if (Array.isArray(value)) {
            for (const entry of value) {
                if (entry != null && String(entry).trim() !== '') {
                    out.push(String(entry));
                }
            }
            continue;
        }
        out.push(String(value));
    }
    return out;
});

const heading = computed(() => {
    if (props.title) {
        return props.title;
    }
    if (messages.value.length === 1) {
        return messages.value[0];
    }
    return t('Please fix the errors below.');
});
</script>

<template>
    <MisAlert
        v-if="messages.length > 0"
        tone="danger"
        :title="heading"
        :class="cn($props.class)"
    >
        <ul
            v-if="messages.length > 1"
            class="list-disc space-y-0.5 ps-4"
        >
            <li
                v-for="(message, index) in messages"
                :key="`${index}-${message}`"
            >
                {{ message }}
            </li>
        </ul>
    </MisAlert>
</template>
