<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import {
    MisDataTable,
    MisPage,
    MisPagination,
    MisSelectField,
    type MisDataTableColumn,
} from '@/components/mis';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import type { RowActionItem } from '@/lib/row-actions';

type UserRow = {
    id: number;
    name: string;
    email: string;
    phone_number?: string | null;
    is_active: boolean;
    orders_count: number;
    total_spent: number;
};

const props = defineProps<{
    items: Paginated<UserRow>;
    filters?: {
        search?: string | null;
        is_active?: boolean | null;
    };
}>();

const { t } = useTranslations();
const search = ref(props.filters?.search ?? '');
const isActive = ref(
    props.filters?.is_active == null
        ? ''
        : props.filters.is_active
          ? '1'
          : '0',
);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.filters,
    (value) => {
        search.value = value?.search ?? '';
        isActive.value =
            value?.is_active == null ? '' : value.is_active ? '1' : '0';
    },
    { deep: true },
);

function applyFilters(nextSearch = search.value): void {
    router.get(
        '/oscar-users',
        {
            search: nextSearch.trim() || undefined,
            is_active: isActive.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        const current = (props.filters?.search ?? '').trim();
        if (next === current) return;
        applyFilters(next);
    }, 300);
});

watch(isActive, () => applyFilters());

const columns = computed<MisDataTableColumn[]>(() => [
    { key: 'name', label: t('Name'), sortable: true },
    { key: 'email', label: t('Email') },
    { key: 'phone_number', label: t('Phone') },
    { key: 'orders_count', label: t('Orders'), align: 'end' },
    { key: 'total_spent', label: t('Spend'), align: 'end' },
    { key: 'is_active', label: t('Active') },
    { key: 'actions', label: '', align: 'end', hideable: false },
]);

const tableRows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        phone_number: item.phone_number || '—',
        total_spent: `€${Number(item.total_spent).toFixed(2)}`,
    })),
);

const activeSelectOptions = [
    { value: '', label: t('All customers') },
    { value: '1', label: t('Active') },
    { value: '0', label: t('Inactive') },
];

function rowActions(item: UserRow): RowActionItem[] {
    return [
        { label: t('View'), icon: Eye, href: `/oscar-users/${item.id}` },
        { label: t('Edit'), icon: Pencil, href: `/oscar-users/${item.id}/edit` },
        {
            label: item.is_active ? t('Disable') : t('Enable'),
            separator: true,
            method: 'patch',
            href: `/oscar-users/${item.id}/toggle-active`,
        },
        {
            label: t('Delete'),
            icon: Trash2,
            variant: 'destructive',
            method: 'delete',
            href: `/oscar-users/${item.id}`,
            confirm: {
                title: t('Delete'),
                description: t('This action cannot be undone.'),
                confirmLabel: t('Delete'),
            },
        },
    ];
}

function itemFromRow(row: Record<string, unknown>): UserRow | null {
    if (typeof row.id !== 'number') return null;
    return row as unknown as UserRow;
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: '/oscar-users' }],
    },
});
</script>

<template>
    <Head :title="t('Customers')" />

    <MisPage>
        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="tableRows"
            row-key="id"
            toolbar
            searchable
            filterable
            :filters-active="Boolean(isActive)"
            column-toggle
            export-filename="customers"
            :search-placeholder="t('Search customers…')"
            :empty-label="t('No customers found')"
            clickable
            @row-click="(row) => router.visit(`/oscar-users/${row.id as number}`)"
        >
            <template #actions>
                <Can permission="oscar_users.create">
                    <Link
                        href="/oscar-users/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>

            <template #filters>
                <MisSelectField
                    id="is_active"
                    v-model="isActive"
                    :label="t('Status')"
                    :options="activeSelectOptions"
                />
            </template>

            <template #cell-is_active="{ row }">
                <Badge
                    :variant="itemFromRow(row)?.is_active ? 'default' : 'outline'"
                >
                    {{
                        itemFromRow(row)?.is_active ? t('Active') : t('Inactive')
                    }}
                </Badge>
            </template>

            <template #cell-actions="{ row }">
                <div v-if="itemFromRow(row)" class="flex justify-end">
                    <RowActionsMenu :actions="rowActions(itemFromRow(row)!)" />
                </div>
            </template>

            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </MisPage>
</template>
