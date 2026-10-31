<script setup lang="ts">
import { Shield, Users } from '@lucide/vue';
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
            id: 'users',
            href: '/settings/users',
            label: t('Users'),
            icon: Users,
            show: can('users.view'),
        },
        {
            id: 'roles',
            href: '/settings/roles',
            label: t('Roles'),
            icon: Shield,
            show: can('roles.view'),
        },
    ]
        .filter((tab) => tab.show)
        .map(({ show: _show, ...tab }) => tab),
);

const active = computed(() =>
    currentUrl.value.startsWith('/settings/roles') ? 'roles' : 'users',
);

const model = computed({
    get: () => active.value,
    set: () => undefined,
});
</script>

<template>
    <MisTabs v-if="tabs.length > 1" v-model="model" :tabs="tabs" class="mb-0" />
</template>
