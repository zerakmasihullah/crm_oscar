<script setup lang="ts">
import { DatabaseBackup, FolderOpen, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { MisTabs } from '@/components/mis';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const { can, canAny } = usePermissions();
const { currentUrl } = useCurrentUrl();

const tabs = computed(() =>
    [
        {
            id: 'files',
            href: '/settings/file-manager',
            label: t('Files'),
            icon: FolderOpen,
            show: can('storage.view'),
        },
        {
            id: 'trash',
            href: '/settings/trash',
            label: t('Trash'),
            icon: Trash2,
            show: can('trash.view'),
        },
        {
            id: 'backups',
            href: '/settings/backups',
            label: t('Backups'),
            icon: DatabaseBackup,
            show:
                can('backups.view') ||
                canAny(['backups.run', 'backups.download']),
        },
    ]
        .filter((tab) => tab.show)
        .map(({ show: _show, ...tab }) => tab),
);

const active = computed(() => {
    const path = currentUrl.value;
    if (path.startsWith('/settings/trash')) {
        return 'trash';
    }
    if (path.startsWith('/settings/backups')) {
        return 'backups';
    }
    return 'files';
});

const model = computed({
    get: () => active.value,
    set: () => undefined,
});
</script>

<template>
    <MisTabs v-if="tabs.length > 1" v-model="model" :tabs="tabs" class="mb-0" />
</template>
