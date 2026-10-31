<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Scale,
    ShoppingBag,
    Store,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DashboardPillBars from '@/components/dashboard/DashboardPillBars.vue';
import FinanceDonut from '@/components/finance/FinanceDonut.vue';
import FinanceDualLine from '@/components/finance/FinanceDualLine.vue';
import FinanceProgressList from '@/components/finance/FinanceProgressList.vue';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type ChartBlock = {
    labels: string[];
    datasets: Array<{ label: string; data: number[] }>;
};

type BreakdownRow = {
    id?: number;
    key?: string;
    name: string;
    total: number;
    count: number;
};

type FilterOption = { value: string; label: string };

const props = defineProps<{
    summary: {
        system_revenue: number;
        system_orders: number;
        platform_revenue: number;
        platform_entries: number;
        expense_total: number;
        expense_entries: number;
        income_total: number;
        net: number;
        period_label: string;
    };
    charts: {
        trend: ChartBlock;
        order_sources: ChartBlock;
        platforms: ChartBlock;
        categories: ChartBlock;
    };
    by_source: BreakdownRow[];
    by_platform: BreakdownRow[];
    by_category: BreakdownRow[];
    can_orders: boolean;
    can_revenue: boolean;
    can_expenses: boolean;
    filter_options: {
        platforms: FilterOption[];
        categories: FilterOption[];
    };
    filters: {
        period: string;
        date: string;
        week: string;
        month: string;
        year: string;
        date_from: string;
        date_to: string;
        order_source: string | null;
        platform_id: number | null;
        category_id: number | null;
    };
}>();

const { t } = useTranslations();

const period = ref(props.filters.period ?? 'month');
const date = ref(props.filters.date);
const week = ref(props.filters.week);
const month = ref(props.filters.month);
const year = ref(props.filters.year);
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const orderSource = ref(props.filters.order_source ?? '');
const platformId = ref(
    props.filters.platform_id != null ? String(props.filters.platform_id) : '',
);
const categoryId = ref(
    props.filters.category_id != null ? String(props.filters.category_id) : '',
);

const periodChips = computed(() => [
    { id: 'day', label: t('Day') },
    { id: 'week', label: t('Week') },
    { id: 'month', label: t('Month') },
    { id: 'year', label: t('Year') },
    { id: 'custom', label: t('Custom') },
    { id: 'all', label: t('All') },
]);

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    return Array.from({ length: 6 }, (_, i) => String(current - i));
});

function applyFilters() {
    router.get(
        '/finance/reports',
        {
            period: period.value || undefined,
            date: period.value === 'day' ? date.value || undefined : undefined,
            week: period.value === 'week' ? week.value || undefined : undefined,
            month:
                period.value === 'month' ? month.value || undefined : undefined,
            year: period.value === 'year' ? year.value || undefined : undefined,
            date_from:
                period.value === 'custom'
                    ? dateFrom.value || undefined
                    : undefined,
            date_to:
                period.value === 'custom'
                    ? dateTo.value || undefined
                    : undefined,
            order_source: orderSource.value || undefined,
            platform_id: platformId.value || undefined,
            category_id: categoryId.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(
    [period, date, week, month, year, dateFrom, dateTo, orderSource, platformId, categoryId],
    () => applyFilters(),
);

function money(value: number): string {
    return `€${Number(value).toFixed(2)}`;
}

function moneyShort(value: number): string {
    return `€${Number(value).toFixed(0)}`;
}

const trendLabels = computed(() => props.charts.trend.labels);

const incomeSeries = computed(() => {
    const labels = trendLabels.value;
    const datasets = props.charts.trend.datasets;
    const system = datasets.find((d) =>
        d.label.toLowerCase().includes('system'),
    )?.data;
    const platform = datasets.find((d) =>
        d.label.toLowerCase().includes('platform'),
    )?.data;
    return labels.map((_, i) => {
        const a = Number(system?.[i]) || 0;
        const b = Number(platform?.[i]) || 0;
        return a + b;
    });
});

const expenseSeries = computed(() => {
    const data =
        props.charts.trend.datasets.find((d) =>
            d.label.toLowerCase().includes('expense'),
        )?.data ?? [];
    return trendLabels.value.map((_, i) => Number(data[i]) || 0);
});

const hasTrend = computed(() =>
    trendLabels.value.some(
        (_, i) =>
            (incomeSeries.value[i] ?? 0) > 0 || (expenseSeries.value[i] ?? 0) > 0,
    ),
);

const sourceItems = computed(() =>
    props.by_source.map((row) => ({
        label: row.name,
        value: row.total,
        meta: `${row.count} ${t('orders')}`,
    })),
);

const platformItems = computed(() =>
    props.by_platform.map((row) => ({
        label: row.name,
        value: row.total,
        meta: `${row.count} ${t('entries')}`,
    })),
);

const categoryItems = computed(() =>
    props.by_category.map((row) => ({
        label: row.name,
        value: row.total,
        meta: `${row.count} ${t('entries')}`,
    })),
);

const donutItems = computed(() => {
    if (props.can_revenue && props.by_platform.length) {
        return props.by_platform.map((row) => ({
            label: row.name,
            value: row.total,
        }));
    }
    const items = [];
    if (props.can_orders && props.summary.system_revenue > 0) {
        items.push({
            label: t('System orders'),
            value: props.summary.system_revenue,
        });
    }
    if (props.can_revenue && props.summary.platform_revenue > 0) {
        items.push({
            label: t('Platform revenue'),
            value: props.summary.platform_revenue,
        });
    }
    return items;
});

const platformBarLabels = computed(() =>
    props.by_platform.slice(0, 8).map((r) => r.name.split(' ')[0] ?? r.name),
);
const platformBarValues = computed(() =>
    props.by_platform.slice(0, 8).map((r) => r.total),
);

const selectClass =
    'h-9 rounded-full border-0 bg-zinc-900/[0.05] px-3 text-xs font-medium text-foreground outline-none ring-1 ring-transparent transition focus:bg-white focus:ring-zinc-900/10 dark:bg-white/5 dark:focus:bg-white/10 dark:focus:ring-white/15';

defineOptions(resourcePageBreadcrumbs('Reports', '/finance/reports'));
</script>

<template>
    <Head :title="t('Finance reports')" />

    <div class="flex w-full flex-1 flex-col gap-5">
        <!-- Compact filter strip -->
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold tracking-tight">
                        {{ t('Finance reports') }}
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.period_label }}
                    </p>
                </div>

                <div
                    class="inline-flex flex-wrap items-center gap-1 rounded-full bg-zinc-900/[0.04] p-1 dark:bg-white/5"
                >
                    <button
                        v-for="chip in periodChips"
                        :key="chip.id"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                        :class="
                            period === chip.id
                                ? 'bg-foreground text-background shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="period = chip.id"
                    >
                        {{ chip.label }}
                    </button>
                </div>
            </div>

            <div
                v-if="period === 'day' || period === 'week' || period === 'month' || period === 'year' || period === 'custom' || can_orders || can_revenue || can_expenses"
                class="flex flex-wrap items-center gap-2"
            >
                <input
                    v-if="period === 'day'"
                    v-model="date"
                    type="date"
                    :class="selectClass"
                />
                <input
                    v-else-if="period === 'week'"
                    v-model="week"
                    type="date"
                    :class="selectClass"
                />
                <input
                    v-else-if="period === 'month'"
                    v-model="month"
                    type="month"
                    :class="selectClass"
                />
                <select
                    v-else-if="period === 'year'"
                    v-model="year"
                    :class="selectClass"
                >
                    <option v-for="y in yearOptions" :key="y" :value="y">
                        {{ y }}
                    </option>
                </select>
                <template v-else-if="period === 'custom'">
                    <input v-model="dateFrom" type="date" :class="selectClass" />
                    <span class="text-xs text-muted-foreground">→</span>
                    <input v-model="dateTo" type="date" :class="selectClass" />
                </template>

                <select
                    v-if="can_orders"
                    v-model="orderSource"
                    :class="selectClass"
                >
                    <option value="">{{ t('All sources') }}</option>
                    <option value="website">{{ t('Website') }}</option>
                    <option value="mobile">{{ t('Mobile App') }}</option>
                    <option value="admin">{{ t('Admin / POS') }}</option>
                </select>
                <select
                    v-if="can_revenue"
                    v-model="platformId"
                    :class="selectClass"
                >
                    <option value="">{{ t('All platforms') }}</option>
                    <option
                        v-for="p in filter_options.platforms"
                        :key="p.value"
                        :value="p.value"
                    >
                        {{ p.label }}
                    </option>
                </select>
                <select
                    v-if="can_expenses"
                    v-model="categoryId"
                    :class="selectClass"
                >
                    <option value="">{{ t('All categories') }}</option>
                    <option
                        v-for="c in filter_options.categories"
                        :key="c.value"
                        :value="c.value"
                    >
                        {{ c.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-if="can_orders"
                class="dash-card flex items-start gap-3 p-4"
            >
                <span
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-sky-500/15 text-sky-600 dark:text-sky-300"
                >
                    <ShoppingBag class="size-5" stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-muted-foreground">
                        {{ t('System orders') }}
                    </p>
                    <p class="mt-1 text-xl font-bold tabular-nums tracking-tight">
                        {{ moneyShort(summary.system_revenue) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        {{ summary.system_orders }} {{ t('orders') }}
                    </p>
                </div>
            </article>

            <article
                v-if="can_revenue"
                class="dash-card flex items-start gap-3 p-4"
            >
                <span
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-300"
                >
                    <Store class="size-5" stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-muted-foreground">
                        {{ t('Platform revenue') }}
                    </p>
                    <p class="mt-1 text-xl font-bold tabular-nums tracking-tight">
                        {{ moneyShort(summary.platform_revenue) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        {{ summary.platform_entries }} {{ t('entries') }}
                    </p>
                </div>
            </article>

            <article
                v-if="can_expenses"
                class="dash-card flex items-start gap-3 p-4"
            >
                <span
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[var(--brand-accent)]/15 text-[var(--brand-accent)]"
                >
                    <Wallet class="size-5" stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-muted-foreground">
                        {{ t('Expenses') }}
                    </p>
                    <p class="mt-1 text-xl font-bold tabular-nums tracking-tight">
                        {{ moneyShort(summary.expense_total) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        {{ summary.expense_entries }} {{ t('entries') }}
                    </p>
                </div>
            </article>

            <article
                class="dash-card flex items-start gap-3 p-4"
                :class="
                    summary.net >= 0
                        ? 'bg-foreground text-background dark:bg-white dark:text-zinc-900'
                        : ''
                "
            >
                <span
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl"
                    :class="
                        summary.net >= 0
                            ? 'bg-white/15 text-inherit'
                            : 'bg-destructive/15 text-destructive'
                    "
                >
                    <Scale class="size-5" stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs"
                        :class="
                            summary.net >= 0
                                ? 'opacity-70'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ t('Net') }}
                    </p>
                    <p class="mt-1 text-xl font-bold tabular-nums tracking-tight">
                        {{ moneyShort(summary.net) }}
                    </p>
                    <p
                        class="mt-0.5 text-[11px]"
                        :class="
                            summary.net >= 0
                                ? 'opacity-60'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ money(summary.income_total) }} −
                        {{ money(summary.expense_total) }}
                    </p>
                </div>
            </article>
        </div>

        <!-- Main charts -->
        <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
            <FinanceDualLine
                v-if="hasTrend"
                :title="t('Income vs expenses')"
                :subtitle="summary.period_label"
                :labels="trendLabels"
                :income="incomeSeries"
                :expenses="expenseSeries"
                :income-label="t('Income')"
                :expense-label="t('Expenses')"
            />
            <div
                v-else
                class="dash-card flex min-h-48 items-center justify-center p-6 text-sm text-muted-foreground"
            >
                {{ t('No data in this period') }}
            </div>

            <FinanceDonut
                :title="
                    can_revenue && by_platform.length
                        ? t('Platforms')
                        : t('Income mix')
                "
                :subtitle="
                    can_revenue && by_platform.length
                        ? t('Who brings the money')
                        : t('System vs platforms')
                "
                :items="donutItems"
                :center-label="t('Total')"
            />
        </div>

        <!-- Readable breakdowns -->
        <div class="grid gap-4 lg:grid-cols-3">
            <FinanceProgressList
                v-if="can_orders"
                :title="t('Order sources')"
                :subtitle="t('Website, app, admin')"
                :items="sourceItems"
            />
            <FinanceProgressList
                v-if="can_revenue"
                :title="t('Platforms')"
                :subtitle="t('External channels')"
                :items="platformItems"
            />
            <FinanceProgressList
                v-if="can_expenses"
                :title="t('Expense categories')"
                :subtitle="t('Where money went')"
                :items="categoryItems"
                :accent-index="0"
            />
        </div>

        <DashboardPillBars
            v-if="can_revenue && platformBarValues.length"
            :title="t('Platform revenue')"
            :subtitle="t('Top channels this period')"
            :total="moneyShort(summary.platform_revenue)"
            :labels="platformBarLabels"
            :values="platformBarValues"
        />
    </div>
</template>

<style src="@/components/dashboard/dash-card.css"></style>
