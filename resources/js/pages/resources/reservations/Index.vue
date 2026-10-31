<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import {
    MisDataTable,
    MisPagination,
    MisSelectField,
} from '@/components/mis';
import { useMisPage } from '@/composables/useMisPage';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/lib/format';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type ReservationRecord = {
    id: number;
    name: string;
    email: string | null;
    phone: string;
    person: string;
    reservation_date: string | null;
    reservation_time: string;
    message: string | null;
    accepted: boolean;
};

const props = defineProps<{
    items: Paginated<ReservationRecord>;
    filters?: { search?: string | null };
}>();

const { t, editAction, deleteAction, viewAction } = useMisPage();
const { can } = usePermissions();
const search = ref(props.filters?.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        if (next === (props.filters?.search ?? '').trim()) return;
        router.get(
            '/reservations',
            { search: next || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 300);
});

const columns = computed(() => [
    { key: 'name', label: t('Name') },
    { key: 'email', label: t('Email') },
    { key: 'phone', label: t('Phone'), width: '8rem' },
    { key: 'person', label: t('Guests'), width: '5rem' },
    { key: 'reservation_date', label: t('Date'), width: '8rem' },
    { key: 'reservation_time', label: t('Time'), width: '6rem' },
    { key: 'accepted', label: t('Status'), width: '9rem' },
    { key: 'actions', label: '', width: '3rem' },
]);

function setAccepted(item: ReservationRecord, value: string) {
    if (!can('reservations.edit')) return;
    router.patch(
        `/reservations/${item.id}/accepted`,
        { accepted: value === '1' },
        { preserveScroll: true },
    );
}

function actionsFor(item: ReservationRecord) {
    return [
        viewAction(`/reservations/${item.id}`),
        editAction(`/reservations/${item.id}/edit`, 'reservations.edit'),
        deleteAction(
            {
                href: `/reservations/${item.id}`,
                title: t('Delete reservation'),
                description: t('Delete reservation for :name?', {
                    name: item.name,
                }),
            },
            'reservations.delete',
        ),
    ];
}

defineOptions(resourcePageBreadcrumbs('Reservations', '/reservations'));
</script>

<template>
    <Head :title="t('Reservations')" />

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
            :search-placeholder="t('Search reservations…')"
            :empty-label="t('No reservations found')"
        >
            <template #actions>
                <Can permission="reservations.create">
                    <Link
                        href="/reservations/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>
            <template #cell-accepted="{ row }">
                <MisSelectField
                    v-if="can('reservations.edit')"
                    :model-value="row.accepted ? '1' : '0'"
                    :options="[
                        { value: '1', label: t('Accepted') },
                        { value: '0', label: t('Rejected') },
                    ]"
                    class="min-w-[8rem]"
                    @update:model-value="(v) => setAccepted(row as ReservationRecord, v)"
                />
                <span v-else class="text-sm">
                    {{ row.accepted ? t('Accepted') : t('Rejected') }}
                </span>
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as ReservationRecord)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </SettingsPage>
</template>
