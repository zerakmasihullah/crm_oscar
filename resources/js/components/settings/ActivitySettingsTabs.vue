<script setup lang="ts">
import { ClipboardList, History, ScrollText } from '@lucide/vue';
import { computed } from 'vue';
import { MisTabs } from '@/components/mis';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const { can } = usePermissions();
const { currentUrl } = useCurrentUrl();

const tabs = computed(() =>
    [
        {
            id: 'login',
            href: '/settings/login-logs',
            label: t('Login logs'),
            icon: History,
            show: can('audit.view'),
        },
        {
            id: 'action',
            href: '/settings/action-logs',
            label: t('Action logs'),
            icon: ClipboardList,
            show: can('audit.view'),
        },
        {
            id: 'system',
            href: '/settings/system-logs',
            label: t('System logs'),
            icon: ScrollText,
            show: can('audit.view'),
        },
    ]
        .filter((tab) => tab.show)
        .map(({ show: _show, ...tab }) => tab),
);

const active = computed(() => {
    if (currentUrl.value.startsWith('/settings/system-logs')) {
        return 'system';
    }
    if (currentUrl.value.startsWith('/settings/action-logs')) {
        return 'action';
    }

    return 'login';
});

const model = computed({
    get: () => active.value,
    set: () => undefined,
});
</script>

<template>
    <MisTabs v-if="tabs.length > 1" v-model="model" :tabs="tabs" class="mb-0" />
</template>
