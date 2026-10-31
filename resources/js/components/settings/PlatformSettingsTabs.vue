<script setup lang="ts">
import { KeyRound, Settings2, Wifi } from '@lucide/vue';
import { computed } from 'vue';
import { MisTabs } from '@/components/mis';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const { can } = usePermissions();
const { currentUrl } = useCurrentUrl();

const tabs = computed(() => {
    if (!can('settings.manage')) {
        return [];
    }

    return [
        {
            id: 'system',
            href: '/settings/system-control',
            label: t('Features'),
            icon: Settings2,
        },
        {
            id: 'offline',
            href: '/settings/offline',
            label: t('Offline & PWA'),
            icon: Wifi,
        },
        {
            id: 'google',
            href: '/settings/google-auth',
            label: t('Google Auth'),
            icon: KeyRound,
        },
    ];
});

const active = computed(() => {
    const path = currentUrl.value;
    if (path.startsWith('/settings/offline')) {
        return 'offline';
    }
    if (path.startsWith('/settings/google-auth')) {
        return 'google';
    }
    return 'system';
});

const model = computed({
    get: () => active.value,
    set: () => undefined,
});
</script>

<template>
    <MisTabs v-if="tabs.length > 1" v-model="model" :tabs="tabs" class="mb-0" />
</template>
