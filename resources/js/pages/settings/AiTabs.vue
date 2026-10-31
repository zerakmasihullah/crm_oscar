<script setup lang="ts">
import {
    Bot,
    Database,
    History,
    MessageSquareText,
    Sparkles,
    WandSparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import { MisTabs } from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    active: 'general' | 'prompts' | 'assist' | 'tasks' | 'data' | 'logs';
    canManage: boolean;
    canViewLogs: boolean;
}>();

const { t } = useTranslations();

const tabs = computed(() =>
    [
        {
            id: 'general',
            href: '/settings/ai',
            label: t('General'),
            icon: Sparkles,
            show: props.canManage,
        },
        {
            id: 'prompts',
            href: '/settings/ai/prompts',
            label: t('Prompts'),
            icon: MessageSquareText,
            show: props.canManage,
        },
        {
            id: 'assist',
            href: '/settings/ai/assist',
            label: t('Assist'),
            icon: WandSparkles,
            show: props.canManage,
        },
        {
            id: 'tasks',
            href: '/settings/ai/tasks',
            label: t('Tasks'),
            icon: Bot,
            show: props.canManage,
        },
        {
            id: 'data',
            href: '/settings/ai/data',
            label: t('Data access'),
            icon: Database,
            show: props.canManage,
        },
        {
            id: 'logs',
            href: '/settings/ai/logs',
            label: t('Usage'),
            icon: History,
            show: props.canViewLogs,
        },
    ]
        .filter((tab) => tab.show)
        .map(({ show: _show, ...item }) => item),
);

const model = computed({
    get: () => props.active,
    set: () => undefined,
});
</script>

<template>
    <MisTabs v-model="model" :tabs="tabs" class="mb-0" />
</template>
