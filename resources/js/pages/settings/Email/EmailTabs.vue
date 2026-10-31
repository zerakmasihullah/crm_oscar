<script setup lang="ts">
import { History, LayoutTemplate, Mail } from '@lucide/vue';
import { computed } from 'vue';
import { MisTabs } from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    active: 'configuration' | 'templates' | 'logs';
    canManage: boolean;
    canViewLogs: boolean;
}>();

const { t } = useTranslations();

const tabs = computed(() =>
    [
        {
            id: 'configuration',
            href: '/settings/email',
            label: t('Configuration'),
            icon: Mail,
            show: props.canManage,
        },
        {
            id: 'templates',
            href: '/settings/email/templates',
            label: t('Templates'),
            icon: LayoutTemplate,
            show: props.canManage,
        },
        {
            id: 'logs',
            href: '/settings/email/logs',
            label: t('Log'),
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
