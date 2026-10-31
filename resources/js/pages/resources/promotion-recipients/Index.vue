<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import {
    MisDataTable,
    MisPage,
    MisPagination,
    MisSelectField,
    MisStatusBadge,
} from '@/components/mis';
import { useMisPage } from '@/composables/useMisPage';
import type { Paginated } from '@/lib/format';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type RecipientRecord = {
    id: number;
    email: string;
    status: string;
    attempts: number;
    sent_at: string | null;
    opened_at: string | null;
    clicked_at: string | null;
    last_error: string | null;
    promotion: { id: number; subject: string } | null;
};

const props = defineProps<{
    items: Paginated<RecipientRecord>;
    filters?: {
        search?: string | null;
        promotion_id?: string | null;
        status?: string | null;
        sent?: string | null;
        attempts?: string | null;
    };
    promotions: Array<{ id: number; subject: string }>;
}>();

const { t, viewAction, deleteAction } = useMisPage();
const search = ref(props.filters?.search ?? '');
const promotionId = ref(props.filters?.promotion_id ?? '');
const status = ref(props.filters?.status ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(extra: Record<string, string | undefined> = {}) {
    router.get(
        '/promotion-recipients',
        {
            search: (extra.search ?? search.value.trim()) || undefined,
            promotion_id: (extra.promotion_id ?? promotionId.value) || undefined,
            status: (extra.status ?? status.value) || undefined,
            attempts: props.filters?.attempts || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        if (next === (props.filters?.search ?? '').trim()) return;
        applyFilters({ search: next || undefined });
    }, 300);
});

watch([promotionId, status], () => applyFilters());

const columns = computed(() => [
    { key: 'promotion', label: t('Promotion') },
    { key: 'email', label: t('Email') },
    { key: 'status', label: t('Status'), width: '6rem' },
    { key: 'attempts', label: t('Attempts'), width: '6rem' },
    { key: 'opened', label: t('Opened'), width: '5rem' },
    { key: 'clicked', label: t('Clicked'), width: '5rem' },
    { key: 'actions', label: '', width: '3rem' },
]);

const rows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        promotion_label: item.promotion?.subject ?? '—',
    })),
);

function actionsFor(item: RecipientRecord) {
    return [
        viewAction(`/promotion-recipients/${item.id}`),
        deleteAction(
            {
                href: `/promotion-recipients/${item.id}`,
                title: t('Delete recipient'),
                description: t('Delete tracking row for :email?', {
                    email: item.email,
                }),
            },
            'promotion_recipients.delete',
        ),
    ];
}

defineOptions(
    resourcePageBreadcrumbs('Promotion recipients', '/promotion-recipients'),
);
</script>

<template>
    <Head :title="t('Promotion recipients')" />

    <MisPage>
        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="rows"
            row-key="id"
            toolbar
            searchable
            filterable
            :filters-active="Boolean(promotionId || status)"
            clickable
            :search-placeholder="t('Search recipients…')"
            :empty-label="t('No recipients found')"
            @row-click="
                (row) => router.visit(`/promotion-recipients/${row.id as number}`)
            "
        >
            <template #filters>
                <div class="grid gap-3 sm:grid-cols-2">
                    <MisSelectField
                        id="promotion_id"
                        v-model="promotionId"
                        :label="t('Promotion')"
                        :options="[
                            { value: '', label: t('All') },
                            ...promotions.map((p) => ({
                                value: String(p.id),
                                label: p.subject,
                            })),
                        ]"
                    />
                    <MisSelectField
                        id="status"
                        v-model="status"
                        :label="t('Status')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: 'sent', label: t('Sent') },
                            { value: 'failed', label: t('Failed') },
                            { value: 'queued', label: t('Queued') },
                            { value: 'pending', label: t('Pending') },
                            { value: 'skipped', label: t('Skipped') },
                        ]"
                    />
                </div>
            </template>
            <template #cell-promotion="{ row }">
                {{ row.promotion_label }}
            </template>
            <template #cell-status="{ row }">
                <MisStatusBadge :status="String(row.status ?? 'pending')" />
            </template>
            <template #cell-opened="{ row }">
                {{ row.opened_at ? t('Yes') : t('No') }}
            </template>
            <template #cell-clicked="{ row }">
                {{ row.clicked_at ? t('Yes') : t('No') }}
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as RecipientRecord)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </MisPage>
</template>
