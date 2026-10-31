<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import {
    MisDataTable,
    MisPagination,
    MisStatusBadge,
} from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useMisPage } from '@/composables/useMisPage';
import type { Paginated } from '@/lib/format';

type PlatformRecord = {
    id: number;
    name: string;
    is_active: boolean;
    revenues_count: number;
};

const props = defineProps<{
    items: Paginated<PlatformRecord>;
    filters?: { search?: string | null };
}>();

const { t, editAction, deleteAction } = useMisPage();
const search = ref(props.filters?.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        if (next === (props.filters?.search ?? '').trim()) return;
        router.get(
            '/settings/platforms',
            { search: next || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 300);
});

const columns = computed(() => [
    { key: 'name', label: t('Name') },
    { key: 'revenues_count', label: t('Entries'), width: '7rem' },
    { key: 'is_active', label: t('Active'), width: '6rem', type: 'status' as const },
    { key: 'actions', label: '', width: '3rem' },
]);

function actionsFor(item: PlatformRecord) {
    return [
        editAction(`/settings/platforms/${item.id}/edit`, 'platforms.edit'),
        deleteAction(
            {
                href: `/settings/platforms/${item.id}`,
                title: t('Delete platform'),
                description: t('Delete :name?', { name: item.name }),
            },
            'platforms.delete',
        ),
    ];
}
</script>

<template>
    <Head :title="t('Sales platforms')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="items.data"
            row-key="id"
            toolbar
            searchable
            :search-placeholder="t('Search platforms…')"
            :empty-label="t('No platforms found')"
        >
            <template #actions>
                <Can permission="platforms.create">
                    <Link
                        href="/settings/platforms/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>
            <template #cell-is_active="{ row }">
                <MisStatusBadge
                    :status="row.is_active ? 'active' : 'inactive'"
                    :label="row.is_active ? t('Active') : t('Inactive')"
                />
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as PlatformRecord)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </SettingsPage>
</template>
