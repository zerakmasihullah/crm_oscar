<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Wallet } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import {
    MisDataTable,
    MisInputField,
    MisPage,
    MisPagination,
    MisSelectField,
    MisStatCard,
    MisStatGrid,
} from '@/components/mis';
import { useMisPage } from '@/composables/useMisPage';
import type { Paginated } from '@/lib/format';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type ExpenseRecord = {
    id: number;
    expense_category_id: number;
    category_name: string | null;
    amount: string | number;
    expense_date: string;
    period_end?: string | null;
    vendor: string | null;
    notes: string | null;
};

type CategoryOption = { value: number | string; label: string };

const props = defineProps<{
    items: Paginated<ExpenseRecord>;
    categories: CategoryOption[];
    summary: {
        total_amount: number;
        entry_count: number;
        period_label: string;
        by_category: Array<{
            category_id: number;
            category_name: string;
            total: number;
            entries: number;
        }>;
    };
    filters?: {
        period?: string | null;
        date?: string | null;
        week?: string | null;
        month?: string | null;
        date_from?: string | null;
        date_to?: string | null;
        category_id?: string | null;
        search?: string | null;
    };
}>();

const { t, editAction, deleteAction } = useMisPage();

const search = ref(props.filters?.search ?? '');
const period = ref(props.filters?.period ?? 'month');
const date = ref(props.filters?.date ?? new Date().toISOString().slice(0, 10));
const week = ref(props.filters?.week ?? new Date().toISOString().slice(0, 10));
const month = ref(props.filters?.month ?? new Date().toISOString().slice(0, 7));
const dateFrom = ref(
    props.filters?.date_from ??
        new Date(new Date().getFullYear(), new Date().getMonth(), 1)
            .toISOString()
            .slice(0, 10),
);
const dateTo = ref(
    props.filters?.date_to ?? new Date().toISOString().slice(0, 10),
);
const categoryId = ref(
    props.filters?.category_id != null
        ? String(props.filters.category_id)
        : '',
);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const filtersActive = computed(
    () =>
        period.value !== 'month' ||
        Boolean(categoryId.value) ||
        (period.value === 'month' &&
            month.value !== new Date().toISOString().slice(0, 7)),
);

function applyFilters(extra: Record<string, string | undefined> = {}) {
    router.get(
        '/expenses',
        {
            search: (extra.search ?? search.value.trim()) || undefined,
            period: period.value || undefined,
            date: period.value === 'day' ? date.value || undefined : undefined,
            week: period.value === 'week' ? week.value || undefined : undefined,
            month:
                period.value === 'month' ? month.value || undefined : undefined,
            date_from:
                period.value === 'custom'
                    ? dateFrom.value || undefined
                    : undefined,
            date_to:
                period.value === 'custom'
                    ? dateTo.value || undefined
                    : undefined,
            category_id: categoryId.value || undefined,
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

watch(period, () => applyFilters());
watch([date, week, month, dateFrom, dateTo, categoryId], () => applyFilters());

const columns = computed(() => [
    { key: 'period_label', label: t('Period'), width: '11rem' },
    { key: 'category_name', label: t('Category') },
    { key: 'vendor', label: t('Vendor') },
    { key: 'amount', label: t('Amount'), width: '8rem', align: 'end' as const },
    { key: 'notes', label: t('Notes') },
    { key: 'actions', label: '', width: '3rem' },
]);

function formatPeriod(item: ExpenseRecord): string {
    if (item.period_end && item.period_end !== item.expense_date) {
        return `${item.expense_date} → ${item.period_end}`;
    }
    return item.expense_date;
}

const rows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        period_label: formatPeriod(item),
        amount_label: `€${Number(item.amount).toFixed(2)}`,
        vendor_label: item.vendor?.trim() ? item.vendor : '—',
        notes_label: item.notes?.trim() ? item.notes : '—',
    })),
);

function actionsFor(item: ExpenseRecord) {
    return [
        editAction(`/expenses/${item.id}/edit`, 'expenses.edit'),
        deleteAction(
            {
                href: `/expenses/${item.id}`,
                title: t('Delete expense'),
                description: t('Delete this expense?'),
            },
            'expenses.delete',
        ),
    ];
}

const categoryFilterOptions = computed(() => [
    { value: '', label: t('All categories') },
    ...props.categories.map((c) => ({
        value: String(c.value),
        label: c.label,
    })),
]);

defineOptions(resourcePageBreadcrumbs('Expenses', '/expenses'));
</script>

<template>
    <Head :title="t('Expenses')" />

    <MisPage>
        <MisStatGrid :columns="3">
            <MisStatCard
                :label="t('Total expenses')"
                :value="`€${Number(summary.total_amount).toFixed(2)}`"
                :hint="summary.period_label"
                :icon="Wallet"
                tone="warning"
            />
            <MisStatCard
                :label="t('Entries')"
                :value="summary.entry_count"
                :hint="summary.period_label"
            />
            <MisStatCard
                :label="t('Average')"
                :value="
                    summary.entry_count > 0
                        ? `€${(Number(summary.total_amount) / summary.entry_count).toFixed(2)}`
                        : '€0.00'
                "
                :hint="t('Per entry')"
            />
        </MisStatGrid>

        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="rows"
            row-key="id"
            toolbar
            searchable
            filterable
            :filters-active="filtersActive"
            :search-placeholder="t('Search vendor or notes…')"
            :empty-label="t('No expenses found')"
        >
            <template #actions>
                <Can permission="expenses.create">
                    <Link
                        href="/expenses/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Add expense')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>
            <template #filters>
                <div class="grid gap-3 sm:grid-cols-2">
                    <MisSelectField
                        id="period"
                        v-model="period"
                        :label="t('Period')"
                        :options="[
                            { value: 'day', label: t('Day') },
                            { value: 'week', label: t('Week') },
                            { value: 'month', label: t('Month') },
                            { value: 'custom', label: t('Custom range') },
                            { value: 'all', label: t('All') },
                        ]"
                    />
                    <MisSelectField
                        id="category_id"
                        v-model="categoryId"
                        :label="t('Category')"
                        :options="categoryFilterOptions"
                    />
                    <MisInputField
                        v-if="period === 'day'"
                        id="date"
                        v-model="date"
                        type="date"
                        :label="t('Date')"
                    />
                    <MisInputField
                        v-else-if="period === 'week'"
                        id="week"
                        v-model="week"
                        type="date"
                        :label="t('Week of')"
                        :hint="t('Any day in the week')"
                    />
                    <MisInputField
                        v-else-if="period === 'month'"
                        id="month"
                        v-model="month"
                        type="month"
                        :label="t('Month')"
                    />
                    <template v-else-if="period === 'custom'">
                        <MisInputField
                            id="date_from"
                            v-model="dateFrom"
                            type="date"
                            :label="t('From')"
                        />
                        <MisInputField
                            id="date_to"
                            v-model="dateTo"
                            type="date"
                            :label="t('To')"
                        />
                    </template>
                </div>
            </template>
            <template #cell-amount="{ row }">
                <span class="font-medium tabular-nums">{{
                    row.amount_label
                }}</span>
            </template>
            <template #cell-vendor="{ row }">
                <span class="text-muted-foreground">{{ row.vendor_label }}</span>
            </template>
            <template #cell-notes="{ row }">
                <span class="line-clamp-1 text-muted-foreground">
                    {{ row.notes_label }}
                </span>
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as ExpenseRecord)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </MisPage>
</template>
