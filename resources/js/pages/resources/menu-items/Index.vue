<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    Eye,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import {
    MisDataTable,
    MisPagination,
    MisSelectField,
    MisStatusBadge,
    type MisDataTableColumn,
} from '@/components/mis';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import type { RowActionItem } from '@/lib/row-actions';

type MenuOption = { id: number; title: string };

type MenuItemRow = {
    id: number;
    title: string;
    price?: number | null;
    status: boolean;
    image_url?: string | null;
    description?: string | null;
    menu?: MenuOption | null;
    total_sold?: number;
    total_revenue?: number;
};

const props = defineProps<{
    items: Paginated<MenuItemRow>;
    menus: MenuOption[];
    filters?: {
        search?: string | null;
        menu_id?: string | null;
        status?: string | null;
        has_sizes?: string | null;
    };
}>();

const { t } = useTranslations();

const search = ref(props.filters?.search ?? '');
const menuId = ref(props.filters?.menu_id ?? '');
const status = ref(props.filters?.status ?? '');
const hasSizes = ref(props.filters?.has_sizes ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let syncingFromProps = false;

watch(
    () => props.filters,
    (value) => {
        syncingFromProps = true;
        search.value = value?.search ?? '';
        menuId.value = value?.menu_id ?? '';
        status.value = value?.status ?? '';
        hasSizes.value = value?.has_sizes ?? '';
        syncingFromProps = false;
    },
    { deep: true },
);

function currentQuery(): Record<string, string | undefined> {
    return {
        search: search.value.trim() || undefined,
        menu_id: menuId.value || undefined,
        status: status.value || undefined,
        has_sizes: hasSizes.value || undefined,
    };
}

function applyFilters(overrides: Record<string, string | undefined> = {}): void {
    router.get(
        '/menu-items',
        { ...currentQuery(), ...overrides },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function filtersMatchServer(): boolean {
    return (
        (search.value.trim() || '') === (props.filters?.search ?? '').trim() &&
        (menuId.value || '') === (props.filters?.menu_id ?? '') &&
        (status.value || '') === (props.filters?.status ?? '') &&
        (hasSizes.value || '') === (props.filters?.has_sizes ?? '')
    );
}

watch(search, (value) => {
    if (syncingFromProps) return;
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        if (next === (props.filters?.search ?? '').trim()) return;
        applyFilters({ search: next || undefined });
    }, 300);
});

watch([menuId, status, hasSizes], () => {
    if (syncingFromProps || filtersMatchServer()) return;
    applyFilters();
});

const filtersActive = computed(
    () => Boolean(menuId.value) || Boolean(status.value) || Boolean(hasSizes.value),
);

const menuOptions = computed(() => [
    { value: '', label: t('All menus') },
    ...props.menus.map((menu) => ({
        value: String(menu.id),
        label: menu.title,
    })),
]);

const columns = computed<MisDataTableColumn[]>(() => [
    { key: 'image', label: t('Image'), width: '4rem' },
    { key: 'menu', label: t('Menu') },
    { key: 'title', label: t('Title') },
    { key: 'price', label: t('Price'), align: 'end' },
    { key: 'total_sold', label: t('Sold'), align: 'end' },
    { key: 'total_revenue', label: t('Revenue'), align: 'end' },
    { key: 'status', label: t('Active'), type: 'status' },
    { key: 'actions', label: '', align: 'end', hideable: false },
]);

const tableRows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        menu: item.menu?.title ?? '—',
        price: formatMoney(item.price),
        total_sold: item.total_sold ?? 0,
        total_revenue: formatMoney(item.total_revenue ?? 0),
        status: item.status ? 'active' : 'paused',
    })),
);

function formatMoney(value?: number | null): string {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
}

function itemFromRow(row: Record<string, unknown>): MenuItemRow | null {
    if (typeof row.id !== 'number') return null;
    return props.items.data.find((item) => item.id === row.id) ?? null;
}

function rowActions(item: MenuItemRow): RowActionItem[] {
    return [
        { label: t('View'), icon: Eye, href: `/menu-items/${item.id}` },
        { label: t('Edit'), icon: Pencil, href: `/menu-items/${item.id}/edit` },
        {
            label: item.status ? t('Deactivate') : t('Activate'),
            icon: item.status ? Ban : CheckCircle2,
            method: 'patch',
            href: `/menu-items/${item.id}/toggle-status`,
        },
        {
            label: t('Delete'),
            icon: Trash2,
            separator: true,
            variant: 'destructive',
            method: 'delete',
            href: `/menu-items/${item.id}`,
            confirm: {
                title: t('Delete'),
                description: t('This action cannot be undone.'),
                confirmLabel: t('Delete'),
            },
        },
    ];
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Menu Items', href: '/menu-items' }],
    },
});
</script>

<template>
    <Head :title="t('Menu Items')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="tableRows"
            row-key="id"
            toolbar
            searchable
            filterable
            :filters-active="filtersActive"
            column-toggle
            export-filename="menu-items"
            :search-placeholder="t('Search items…')"
            :empty-label="t('No items found')"
            clickable
            @row-click="(row) => router.visit(`/menu-items/${row.id as number}`)"
        >
            <template #actions>
<Can permission="menu_items.create">
                    <Link
                        href="/menu-items/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>

            <template #filters>
                <div class="grid gap-3 sm:grid-cols-3" @pointerdown.stop>
                    <MisSelectField
                        id="filter-menu"
                        v-model="menuId"
                        :label="t('Menu')"
                        :options="menuOptions"
                    />
                    <MisSelectField
                        id="filter-status"
                        v-model="status"
                        :label="t('Status')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: '1', label: t('Active') },
                            { value: '0', label: t('Inactive') },
                        ]"
                    />
                    <MisSelectField
                        id="filter-sizes"
                        v-model="hasSizes"
                        :label="t('Sizes')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: '1', label: t('Has sizes') },
                            { value: '0', label: t('No sizes') },
                        ]"
                    />
                </div>
            </template>

            <template #cell-image="{ row }">
                <img
                    v-if="itemFromRow(row)?.image_url"
                    :src="itemFromRow(row)!.image_url!"
                    :alt="itemFromRow(row)!.title"
                    class="size-10 rounded-lg object-cover"
                />
                <div
                    v-else
                    class="flex size-10 items-center justify-center rounded-lg bg-muted text-xs text-muted-foreground"
                >
                    —
                </div>
            </template>

            <template #cell-status="{ value }">
                <MisStatusBadge :status="String(value)" />
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
    </SettingsPage>
</template>
