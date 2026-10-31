<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Euro, Plus } from '@lucide/vue';
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

type RevenueRecord = {
    id: number;
    platform_id: number;
    platform_name: string | null;
    amount: string | number;
    revenue_date: string;
    notes: string | null;
};

type PlatformOption = { value: number | string; label: string };

const props = defineProps<{
    items: Paginated<RevenueRecord>;
    platforms: PlatformOption[];
    summary: {
        total_amount: number;
        entry_count: number;
        period_label: string;
        by_platform: Array<{
            platform_id: number;
            platform_name: string;
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
        platform_id?: string | null;
    };
}>();

const { t, editAction, deleteAction } = useMisPage();

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
const platformId = ref(
    props.filters?.platform_id != null
        ? String(props.filters.platform_id)
        : '',
);

const filtersActive = computed(
    () =>
        period.value !== 'month' ||
        Boolean(platformId.value) ||
        (period.value === 'month' &&
            month.value !== new Date().toISOString().slice(0, 7)),
);

function applyFilters() {
    router.get(
        '/platform-revenues',
        {
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
            platform_id: platformId.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(period, () => applyFilters());
watch([date, week, month, dateFrom, dateTo, platformId], () => applyFilters());

const columns = computed(() => [
    { key: 'revenue_date', label: t('Date'), width: '8rem' },
    { key: 'platform_name', label: t('Platform') },
    { key: 'amount', label: t('Amount'), width: '8rem', align: 'end' as const },
    { key: 'notes', label: t('Notes') },
    { key: 'actions', label: '', width: '3rem' },
]);

const rows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        amount_label: `€${Number(item.amount).toFixed(2)}`,
        notes_label: item.notes?.trim() ? item.notes : '—',
    })),
);

function actionsFor(item: RevenueRecord) {
    return [
        editAction(
            `/platform-revenues/${item.id}/edit`,
            'platform_revenues.edit',
        ),
        deleteAction(
            {
                href: `/platform-revenues/${item.id}`,
                title: t('Delete revenue entry'),
                description: t('Delete this revenue entry?'),
            },
            'platform_revenues.delete',
        ),
    ];
}

const platformFilterOptions = computed(() => [
    { value: '', label: t('All platforms') },
    ...props.platforms.map((p) => ({
        value: String(p.value),
        label: p.label,
    })),
]);

defineOptions(
    resourcePageBreadcrumbs('Platform revenue', '/platform-revenues'),
);
</script>

<template>
    <Head :title="t('Platform revenue')" />

    <MisPage>
        <MisStatGrid :columns="3">
            <MisStatCard
                :label="t('Total revenue')"
                :value="`€${Number(summary.total_amount).toFixed(2)}`"
                :hint="summary.period_label"
                :icon="Euro"
                tone="success"
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
            :columns="columns"
            :rows="rows"
            row-key="id"
            toolbar
            filterable
            :filters-active="filtersActive"
            :empty-label="t('No revenue entries found')"
        >
            <template #actions>
                <Can permission="platform_revenues.create">
                    <Link
                        href="/platform-revenues/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Add revenue')"
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
                        id="platform_id"
                        v-model="platformId"
                        :label="t('Platform')"
                        :options="platformFilterOptions"
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
            <template #cell-notes="{ row }">
                <span class="line-clamp-1 text-muted-foreground">
                    {{ row.notes_label }}
                </span>
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as RevenueRecord)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </MisPage>
</template>
