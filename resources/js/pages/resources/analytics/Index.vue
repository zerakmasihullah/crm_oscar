<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CheckCircle2,
    Clock3,
    Euro,
    ShoppingBag,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    MisChartCard,
    MisPage,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
} from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';

type ChartBlock = {
    labels: string[];
    datasets: Array<{ label: string; data: number[] }>;
};

type TopItem = {
    id: number;
    title: string;
    image?: string | null;
    total_quantity: number;
    total_revenue: number;
};

type LatestOrder = {
    id: number;
    customer_name: string;
    total_price: number;
    status: number;
    status_label: string;
    type_label: string;
    items_summary?: string;
    created_at?: string | null;
};

const props = defineProps<{
    stats: {
        revenue_30d: number;
        orders_today: number;
        pending: number;
        completed: number;
    };
    revenueChart: ChartBlock;
    orderStatusChart: ChartBlock;
    topSellingItems: TopItem[];
    latestOrders: LatestOrder[];
}>();

const { t } = useTranslations();

const maxSold = computed(() =>
    Math.max(1, ...props.topSellingItems.map((i) => i.total_quantity)),
);

const statusToneMap: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral' | 'info'
> = {
    pending: 'warning',
    accepted: 'info',
    rejected: 'danger',
    delivering: 'info',
    completed: 'success',
};

function itemImage(item: TopItem): string | null {
    if (!item.image) return null;
    if (item.image.startsWith('http') || item.image.startsWith('/')) {
        return item.image;
    }
    return `/storage/${item.image}`;
}

function relativeTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) return '—';
    const diff = Date.now() - then;
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return t('Just now');
    if (mins < 60) return t(':m min ago', { m: String(mins) });
    const hours = Math.floor(mins / 60);
    if (hours < 24) return t(':h h ago', { h: String(hours) });
    return new Date(iso).toLocaleDateString();
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Analytics', href: '/analytics' }],
    },
});
</script>

<template>
    <Head :title="t('Analytics')" />

    <MisPage>
        <MisStatGrid :columns="4">
            <MisStatCard
                :label="t('Revenue (30d)')"
                :value="`€${Number(stats.revenue_30d).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`"
                :icon="Euro"
                tone="success"
            />
            <MisStatCard
                :label="t('Orders today')"
                :value="stats.orders_today"
                :icon="ShoppingBag"
                tone="info"
            />
            <MisStatCard
                :label="t('Pending')"
                :value="stats.pending"
                :icon="Clock3"
                tone="warning"
            />
            <MisStatCard
                :label="t('Completed')"
                :value="stats.completed"
                :icon="CheckCircle2"
                tone="success"
            />
        </MisStatGrid>

        <div class="grid gap-4 lg:grid-cols-5">
            <MisChartCard
                class="lg:col-span-3"
                :title="t('Revenue')"
                :description="t('Last 30 days · excludes rejected')"
                type="line"
                :labels="revenueChart.labels"
                :datasets="revenueChart.datasets"
            />
            <MisChartCard
                class="lg:col-span-2"
                :title="t('Order status')"
                :description="t('All-time mix')"
                type="pie"
                :labels="orderStatusChart.labels"
                :datasets="orderStatusChart.datasets"
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <!-- Top sellers -->
            <section
                class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-soft dark:border-white/10"
            >
                <div class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-4">
                    <div>
                        <h2 class="text-[15px] font-semibold tracking-tight">
                            {{ t('Top sellers') }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ t('By quantity sold') }}
                        </p>
                    </div>
                    <Link
                        href="/menu-items"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        {{ t('Menu') }}
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </div>

                <ul v-if="topSellingItems.length" class="divide-y divide-border/50">
                    <li
                        v-for="(item, index) in topSellingItems"
                        :key="item.id"
                        class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-muted/30"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold tabular-nums"
                            :class="
                                index === 0
                                    ? 'bg-orange-500 text-white'
                                    : index < 3
                                      ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400'
                                      : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ index + 1 }}
                        </span>

                        <div
                            class="size-10 shrink-0 overflow-hidden rounded-xl bg-muted"
                        >
                            <img
                                v-if="itemImage(item)"
                                :src="itemImage(item)!"
                                :alt="item.title"
                                class="size-full object-cover"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center text-[10px] font-medium text-muted-foreground"
                            >
                                —
                            </div>
                        </div>

                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="truncate text-sm font-medium">
                                    {{ item.title }}
                                </p>
                                <p class="shrink-0 text-sm font-semibold tabular-nums">
                                    €{{ Number(item.total_revenue).toFixed(2) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-orange-500/80 transition-all"
                                        :style="{
                                            width: `${Math.round((item.total_quantity / maxSold) * 100)}%`,
                                        }"
                                    />
                                </div>
                                <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">
                                    {{ item.total_quantity }} {{ t('sold') }}
                                </span>
                            </div>
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="px-5 py-10 text-center text-sm text-muted-foreground"
                >
                    {{ t('No sales data yet') }}
                </p>
            </section>

            <!-- Latest orders -->
            <section
                class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-soft dark:border-white/10"
            >
                <div class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-4">
                    <div>
                        <h2 class="text-[15px] font-semibold tracking-tight">
                            {{ t('Latest orders') }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ t('Most recent activity') }}
                        </p>
                    </div>
                    <Link
                        href="/orders"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        {{ t('View all') }}
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </div>

                <ul v-if="latestOrders.length" class="divide-y divide-border/50">
                    <li
                        v-for="order in latestOrders"
                        :key="order.id"
                    >
                        <Link
                            :href="`/orders/${order.id}`"
                            class="flex items-start gap-3 px-5 py-3.5 transition hover:bg-muted/30"
                        >
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-primary">
                                        #{{ order.id }}
                                    </span>
                                    <span
                                        class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ order.type_label }}
                                    </span>
                                    <MisStatusBadge
                                        :status="order.status_label"
                                        :label="order.status_label"
                                        :map="statusToneMap"
                                    />
                                </div>
                                <p class="truncate text-sm text-foreground">
                                    {{ order.customer_name }}
                                </p>
                                <p
                                    v-if="order.items_summary"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ order.items_summary }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold tabular-nums">
                                    €{{ Number(order.total_price).toFixed(2) }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-muted-foreground">
                                    {{ relativeTime(order.created_at) }}
                                </p>
                            </div>
                        </Link>
                    </li>
                </ul>
                <p
                    v-else
                    class="px-5 py-10 text-center text-sm text-muted-foreground"
                >
                    {{ t('No orders yet') }}
                </p>
            </section>
        </div>
    </MisPage>
</template>
