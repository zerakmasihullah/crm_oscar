<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
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

type DiscountRecord = {
    id: number;
    name: string;
    discount_type: string;
    discount_value: string | number;
    minimum_purchase_amount: string | number | null;
    days_of_week: string[];
    is_active: boolean;
};

const props = defineProps<{
    items: Paginated<DiscountRecord>;
    filters?: {
        search?: string | null;
        is_active?: string | null;
        discount_type?: string | null;
    };
}>();

const { t, editAction, deleteAction, viewAction } = useMisPage();
const search = ref(props.filters?.search ?? '');
const isActive = ref(props.filters?.is_active ?? '');
const discountType = ref(props.filters?.discount_type ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(extra: Record<string, string | undefined> = {}) {
    router.get(
        '/discounts',
        {
            search: (extra.search ?? search.value.trim()) || undefined,
            is_active: (extra.is_active ?? isActive.value) || undefined,
            discount_type: (extra.discount_type ?? discountType.value) || undefined,
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

watch([isActive, discountType], () => applyFilters());

const columns = computed(() => [
    { key: 'name', label: t('Name') },
    { key: 'discount_type', label: t('Type'), width: '8rem' },
    { key: 'discount_value', label: t('Value'), width: '7rem' },
    { key: 'minimum_purchase_amount', label: t('Min. purchase'), width: '8rem' },
    { key: 'days_of_week', label: t('Days') },
    { key: 'is_active', label: t('Active'), width: '6rem', type: 'status' as const },
    { key: 'actions', label: '', width: '3rem' },
]);

const rows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        days_label:
            item.days_of_week?.length > 0
                ? item.days_of_week.map((d) => d.slice(0, 3)).join(', ')
                : t('All days'),
        value_label:
            item.discount_type === 'percentage'
                ? `${Number(item.discount_value)}%`
                : `€${Number(item.discount_value).toFixed(2)}`,
        min_label: item.minimum_purchase_amount
            ? `€${Number(item.minimum_purchase_amount).toFixed(2)}`
            : t('No minimum'),
    })),
);

function actionsFor(item: DiscountRecord) {
    return [
        viewAction(`/discounts/${item.id}`),
        editAction(`/discounts/${item.id}/edit`, 'discounts.edit'),
        deleteAction(
            {
                href: `/discounts/${item.id}`,
                title: t('Delete discount'),
                description: t('Delete :name?', { name: item.name }),
            },
            'discounts.delete',
        ),
    ];
}

defineOptions(resourcePageBreadcrumbs('Discounts', '/discounts'));
</script>

<template>
    <Head :title="t('Discounts')" />

    <MisPage>
        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="rows"
            row-key="id"
            toolbar
            searchable
            filterable
            :filters-active="Boolean(isActive || discountType)"
            :search-placeholder="t('Search discounts…')"
            :empty-label="t('No discounts found')"
            clickable
            @row-click="(row) => router.visit(`/discounts/${row.id as number}`)"
        >
            <template #actions>
                <Can permission="discounts.create">
                    <Link
                        href="/discounts/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>
            <template #filters>
                <div class="grid gap-3 sm:grid-cols-2">
                    <MisSelectField
                        id="is_active"
                        v-model="isActive"
                        :label="t('Status')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: '1', label: t('Active') },
                            { value: '0', label: t('Inactive') },
                        ]"
                    />
                    <MisSelectField
                        id="discount_type"
                        v-model="discountType"
                        :label="t('Type')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: 'percentage', label: t('Percentage') },
                            { value: 'fixed', label: t('Fixed') },
                        ]"
                    />
                </div>
            </template>
            <template #cell-discount_type="{ row }">
                {{ row.discount_type === 'percentage' ? t('Percentage') : t('Fixed') }}
            </template>
            <template #cell-discount_value="{ row }">
                {{ row.value_label }}
            </template>
            <template #cell-minimum_purchase_amount="{ row }">
                {{ row.min_label }}
            </template>
            <template #cell-days_of_week="{ row }">
                {{ row.days_label }}
            </template>
            <template #cell-is_active="{ row }">
                <MisStatusBadge
                    :status="row.is_active ? 'active' : 'inactive'"
                    :label="row.is_active ? t('Active') : t('Inactive')"
                />
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as DiscountRecord)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </MisPage>
</template>
