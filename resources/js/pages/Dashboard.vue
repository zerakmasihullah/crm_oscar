<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, MapPin, Printer, Truck, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Can from '@/components/Can.vue';
import DashboardAreaLine from '@/components/dashboard/DashboardAreaLine.vue';
import DashboardPillBars from '@/components/dashboard/DashboardPillBars.vue';
import DashboardStatusTracks from '@/components/dashboard/DashboardStatusTracks.vue';
import { Button } from '@/components/ui/button';
import {
    MisPage,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
    MisTabs,
} from '@/components/mis';
import type { MisTabItem } from '@/components/mis';
import { mountOrderSound, unmountOrderSound } from '@/composables/useOrderSound';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';

type OrderItemLine = {
    title: string;
    quantity: number;
    size: string | null;
    extras: string[];
};

type TodayOrder = {
    id: number;
    customer_id: number | null;
    customer: string;
    phone: string | null;
    address: string | null;
    latitude: string | number | null;
    longitude: string | number | null;
    delivery_datetime: string | null;
    due_at: string | null;
    subtotal: number;
    discount: number;
    total_price: number;
    payment_method: string | null;
    payment_id: string | null;
    notes: string | null;
    status: number;
    status_label: string;
    type: number;
    type_label: string;
    deliveryman_id: number | null;
    deliveryman: string | null;
    created_at: string | null;
    items: OrderItemLine[];
};

type DriverOption = { id: number; name: string };

const props = defineProps<{
    stats: {
        orders_today: number;
        revenue_today: number;
        pending: number;
        completed_week: number;
    };
    revenueChart: { labels: string[]; data: number[] };
    ordersChart: { labels: string[]; data: number[] };
    statusChart: { label: string; value: number }[];
    todayOrders: TodayOrder[];
    drivers?: DriverOption[];
    today: string;
    canListenSound?: boolean;
}>();

const drivers = computed(() => props.drivers ?? []);

const { t } = useTranslations();
const busyId = ref<number | null>(null);
const activeTab = ref('1');
const nowTick = ref(Date.now());
const driverDraft = ref<Record<number, number | ''>>({});
let pollTimer: ReturnType<typeof setInterval> | null = null;
let tickTimer: ReturnType<typeof setInterval> | null = null;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const statusToneMap: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral' | 'info'
> = {
    Pending: 'warning',
    Accepted: 'info',
    Rejected: 'danger',
    Delivering: 'info',
    Completed: 'success',
};

const statusTabsMeta = [
    { id: '1', label: 'Pending' },
    { id: '2', label: 'Accepted' },
    { id: '4', label: 'Delivering' },
    { id: '5', label: 'Completed' },
    { id: '3', label: 'Rejected' },
] as const;

const ordersByStatus = computed(() => {
    const groups: Record<number, TodayOrder[]> = {
        1: [],
        2: [],
        3: [],
        4: [],
        5: [],
    };

    for (const order of props.todayOrders) {
        const bucket = groups[order.status];
        if (bucket) {
            bucket.push(order);
        }
    }

    for (const status of Object.keys(groups)) {
        groups[Number(status)]!.sort((a, b) => {
            const aDue = a.due_at ? Date.parse(a.due_at) : 0;
            const bDue = b.due_at ? Date.parse(b.due_at) : 0;
            return aDue - bDue;
        });
    }

    return groups;
});

const orderTabs = computed<MisTabItem[]>(() =>
    statusTabsMeta.map((tab) => {
        const count = ordersByStatus.value[Number(tab.id)]?.length ?? 0;
        return {
            id: tab.id,
            label: `${t(tab.label)} (${count})`,
        };
    }),
);

const filteredOrders = computed(
    () => ordersByStatus.value[Number(activeTab.value)] ?? [],
);

const activeTabTotal = computed(() =>
    filteredOrders.value.reduce(
        (sum, order) => sum + (Number(order.total_price) || 0),
        0,
    ),
);

const weekRevenueTotal = computed(() => {
    let sum = 0;
    for (const n of props.revenueChart.data) sum += Number(n) || 0;
    return sum;
});

const weekOrdersTotal = computed(() => {
    let sum = 0;
    for (const n of props.ordersChart.data) sum += Number(n) || 0;
    return sum;
});

function formatClock(date: Date): string {
    return date
        .toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        })
        .replace(/\s/g, '')
        .toLowerCase();
}

function orderTimeRange(order: TodayOrder): string | null {
    const placed = order.created_at ? new Date(order.created_at) : null;
    const due = order.due_at ? new Date(order.due_at) : null;
    if (!placed || Number.isNaN(placed.getTime())) return null;
    if (!due || Number.isNaN(due.getTime())) return formatClock(placed);
    return `${formatClock(placed)} – ${formatClock(due)}`;
}

function dueState(order: TodayOrder): 'overdue' | 'soon' | null {
    void nowTick.value;
    if (!order.due_at || order.status === 5 || order.status === 3) return null;
    const due = new Date(order.due_at).getTime();
    if (Number.isNaN(due)) return null;
    const diff = due - Date.now();
    if (diff < 0) return 'overdue';
    if (diff <= 10 * 60 * 1000) return 'soon';
    return null;
}

function paymentLabel(order: TodayOrder): string | null {
    if (!order.payment_method) return null;
    const method = order.payment_method.toLowerCase();
    if (method === 'stripe') {
        return order.payment_id
            ? t('Stripe · paid')
            : t('Stripe · unpaid');
    }
    return order.payment_method;
}

function paymentTone(order: TodayOrder): string {
    const method = (order.payment_method ?? '').toLowerCase();
    if (method === 'stripe' && !order.payment_id) {
        return 'text-destructive';
    }
    if (method === 'stripe' && order.payment_id) {
        return 'text-emerald-700 dark:text-emerald-300';
    }
    return 'text-muted-foreground';
}

let refreshQueued = false;

function refreshDashboard(full = false): void {
    if (busyId.value !== null) {
        refreshQueued = true;
        return;
    }
    router.reload({
        only: full
            ? [
                  'stats',
                  'revenueChart',
                  'ordersChart',
                  'statusChart',
                  'todayOrders',
                  'drivers',
              ]
            : ['stats', 'statusChart', 'todayOrders'],
        preserveScroll: true,
        preserveState: true,
    });
}

function onNewOrder(): void {
    activeTab.value = '1';
    refreshDashboard(true);
}

function runOrderAction(
    id: number,
    url: string,
    options?: {
        method?: 'post' | 'patch';
        data?: Record<string, unknown>;
        onSuccess?: () => void;
        onError?: () => void;
    },
): void {
    if (busyId.value !== null) return;
    busyId.value = id;
    const method = options?.method ?? 'post';
    const visit = method === 'patch' ? router.patch : router.post;
    visit(url, options?.data ?? {}, {
        preserveScroll: true,
        onSuccess: () => {
            options?.onSuccess?.();
        },
        onError: () => {
            options?.onError?.();
        },
        onFinish: () => {
            busyId.value = null;
            if (refreshQueued) {
                refreshQueued = false;
                refreshDashboard();
            }
        },
    });
}

function mapUrl(order: TodayOrder): string | null {
    const lat =
        order.latitude != null && order.latitude !== ''
            ? Number(order.latitude)
            : NaN;
    const lng =
        order.longitude != null && order.longitude !== ''
            ? Number(order.longitude)
            : NaN;
    if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
        return `https://www.google.com/maps?q=${lat},${lng}`;
    }
    if (order.address?.trim()) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.address.trim())}`;
    }
    return null;
}

function acceptOrder(order: TodayOrder): void {
    if (busyId.value !== null) return;

    // Open during the click gesture so the popup is not blocked.
    const printTab = window.open('about:blank', '_blank');
    if (printTab) {
        try {
            printTab.document.write(
                `<p style="font:14px sans-serif;padding:16px">Accepting order #${order.id}…</p>`,
            );
            printTab.document.close();
        } catch {
            // ignore cross-window write failures
        }
    }

    busyId.value = order.id;
    router.post(
        `/orders/${order.id}/accept`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                activeTab.value = '2';
                const printUrl = `/orders/${order.id}/print`;
                if (printTab && !printTab.closed) {
                    printTab.location.href = printUrl;
                    printTab.focus();
                    return;
                }
                window.open(printUrl, '_blank');
            },
            onError: () => {
                printTab?.close();
            },
            onFinish: () => {
                busyId.value = null;
                if (refreshQueued) {
                    refreshQueued = false;
                    refreshDashboard();
                }
            },
        },
    );
}

function rejectOrder(order: TodayOrder): void {
    if (
        !window.confirm(
            t('Reject this order? The customer will be notified.'),
        )
    ) {
        return;
    }
    runOrderAction(order.id, `/orders/${order.id}/reject`);
}

function setStatus(
    order: TodayOrder,
    status: number,
    deliverymanId?: number | null,
): void {
    runOrderAction(order.id, `/orders/${order.id}/status`, {
        method: 'patch',
        data: {
            status,
            deliveryman_id: deliverymanId ?? order.deliveryman_id,
        },
    });
}

function startDelivery(order: TodayOrder): void {
    const selected =
        driverDraft.value[order.id] !== undefined
            ? driverDraft.value[order.id]
            : order.deliveryman_id;
    const driverId =
        selected === '' || selected == null ? null : Number(selected);
    if (!driverId) {
        window.alert(t('Select a driver first.'));
        return;
    }
    setStatus(order, 4, driverId);
}

function completeOrder(order: TodayOrder): void {
    setStatus(order, 5);
}

onMounted(() => {
    if (props.canListenSound !== false) {
        mountOrderSound(onNewOrder);
    }
    // Light poll for queue; charts refresh on new-order events.
    pollTimer = window.setInterval(() => refreshDashboard(false), 45_000);
    tickTimer = window.setInterval(() => {
        nowTick.value = Date.now();
    }, 60_000);
});

onUnmounted(() => {
    if (props.canListenSound !== false) {
        unmountOrderSound(onNewOrder);
    }
    if (pollTimer) window.clearInterval(pollTimer);
    if (tickTimer) window.clearInterval(tickTimer);
});
</script>

<template>
    <Head :title="t('Dashboard')" />

    <MisPage>
        <MisStatGrid :columns="4">
            <MisStatCard
                :label="t('Orders today')"
                :value="String(stats.orders_today)"
            />
            <MisStatCard
                :label="t('Revenue today')"
                :value="`€${Number(stats.revenue_today).toFixed(2)}`"
            />
            <MisStatCard
                :label="t('Pending')"
                :value="String(stats.pending)"
                tone="warning"
            />
            <MisStatCard
                :label="t('Completed (7d)')"
                :value="String(stats.completed_week)"
                tone="success"
            />
        </MisStatGrid>

        <div class="grid gap-4 xl:grid-cols-2 xl:items-start">
            <div class="space-y-4">
                <DashboardAreaLine
                    :title="t('Revenue')"
                    :subtitle="t('Last 7 days')"
                    :total="`€${weekRevenueTotal.toFixed(2)}`"
                    :labels="revenueChart.labels"
                    :values="revenueChart.data"
                />
                <DashboardPillBars
                    :title="t('Orders')"
                    :subtitle="t('Last 7 days')"
                    :total="String(weekOrdersTotal)"
                    :labels="ordersChart.labels"
                    :values="ordersChart.data"
                />
                <DashboardStatusTracks
                    :title="t(`Today's status`)"
                    :subtitle="t('Orders placed today')"
                    :items="statusChart"
                />
            </div>

            <aside
                class="dash-card flex max-h-[calc(100vh-6rem)] flex-col overflow-hidden xl:sticky xl:top-4"
            >
                <div class="shrink-0 space-y-3 px-4 pb-3 pt-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-[15px] font-semibold tracking-tight">
                            {{ t(`Today's orders`) }}
                        </h2>
                        <Link
                            :href="`/orders?date_from=${today}&date_to=${today}`"
                            class="text-sm font-medium text-primary hover:underline"
                        >
                            {{ t('View all') }}
                        </Link>
                    </div>
                    <MisTabs
                        v-model="activeTab"
                        :tabs="orderTabs"
                        class="w-full"
                    />
                </div>

                <div
                    class="orders-scroll min-h-0 flex-1 space-y-3 overflow-y-auto px-3 pb-4"
                >
                    <article
                        v-for="order in filteredOrders"
                        :key="order.id"
                        class="rounded-2xl border p-4"
                        :class="[
                            busyId === order.id ? 'opacity-60' : '',
                            dueState(order) === 'overdue'
                                ? 'border-destructive/40 bg-destructive/5'
                                : dueState(order) === 'soon'
                                  ? 'border-amber-500/40 bg-amber-500/5'
                                  : 'border-border/60 bg-card/80 dark:border-white/10',
                        ]"
                    >
                        <div class="flex gap-3">
                            <div class="min-w-0 flex-1 space-y-2.5">
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 flex-wrap items-center gap-2"
                                    >
                                        <Link
                                            :href="`/orders/${order.id}`"
                                            class="text-sm font-semibold text-primary hover:underline"
                                        >
                                            #{{ order.id }}
                                        </Link>
                                        <span
                                            class="inline-flex rounded-full bg-[var(--brand-accent)] px-2.5 py-0.5 text-xs font-bold text-[#111318]"
                                        >
                                            {{ order.type_label }}
                                        </span>
                                        <MisStatusBadge
                                            :status="order.status_label"
                                            :label="order.status_label"
                                            :map="statusToneMap"
                                        />
                                        <span
                                            v-if="dueState(order) === 'overdue'"
                                            class="rounded-full bg-destructive px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                                        >
                                            {{ t('Overdue') }}
                                        </span>
                                        <span
                                            v-else-if="dueState(order) === 'soon'"
                                            class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#111318]"
                                        >
                                            {{ t('Due soon') }}
                                        </span>
                                    </div>
                                    <p
                                        class="shrink-0 text-xl font-bold tabular-nums tracking-tight text-foreground"
                                    >
                                        €{{
                                            Number(order.total_price).toFixed(2)
                                        }}
                                    </p>
                                </div>

                                <p
                                    v-if="orderTimeRange(order)"
                                    class="text-[15px] font-semibold tabular-nums tracking-tight text-foreground"
                                >
                                    {{ orderTimeRange(order) }}
                                </p>

                                <div
                                    v-if="order.type === 1 && order.address"
                                    class="flex items-start gap-1.5"
                                >
                                    <a
                                        v-if="mapUrl(order)"
                                        :href="mapUrl(order)!"
                                        target="_blank"
                                        rel="noopener"
                                        class="mt-0.5 shrink-0 rounded-md p-0.5 text-primary hover:bg-primary/10"
                                        :title="t('Map')"
                                    >
                                        <MapPin class="size-3.5" />
                                    </a>
                                    <MapPin
                                        v-else
                                        class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                    />
                                    <p
                                        class="min-w-0 text-sm leading-snug text-foreground/90"
                                    >
                                        {{ order.address }}
                                    </p>
                                </div>

                                <p class="truncate text-xs">
                                    <Link
                                        v-if="order.customer_id"
                                        :href="`/oscar-users/${order.customer_id}`"
                                        class="font-medium text-foreground/80 hover:text-primary hover:underline"
                                    >
                                        {{ order.customer }}
                                    </Link>
                                    <span
                                        v-else
                                        class="text-muted-foreground"
                                        >{{ order.customer }}</span
                                    >
                                    <template v-if="order.phone">
                                        <span class="text-muted-foreground">
                                            · {{ order.phone }}
                                        </span>
                                    </template>
                                    <template v-if="paymentLabel(order)">
                                        <span :class="paymentTone(order)">
                                            · {{ paymentLabel(order) }}
                                        </span>
                                    </template>
                                    <template v-if="order.deliveryman">
                                        <span class="text-muted-foreground">
                                            · {{ order.deliveryman }}
                                        </span>
                                    </template>
                                </p>

                                <p
                                    v-if="order.notes"
                                    class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-1.5 text-xs font-medium leading-snug text-amber-950 dark:text-amber-100"
                                >
                                    {{ t('Notes') }}: {{ order.notes }}
                                </p>

                                <ul
                                    v-if="order.items?.length"
                                    class="space-y-1 border-t border-border/50 pt-2 dark:border-white/10"
                                >
                                    <li
                                        v-for="(item, index) in order.items"
                                        :key="`${order.id}-${index}`"
                                        class="text-sm leading-snug"
                                    >
                                        <p>
                                            <span
                                                class="font-semibold tabular-nums"
                                                >{{ item.quantity }}×</span
                                            >
                                            {{ item.title }}
                                            <span
                                                v-if="item.size"
                                                class="text-muted-foreground"
                                            >
                                                ({{ item.size }})
                                            </span>
                                        </p>
                                        <p
                                            v-if="item.extras?.length"
                                            class="ps-5 text-xs text-muted-foreground"
                                        >
                                            {{ item.extras.join(', ') }}
                                        </p>
                                    </li>
                                </ul>

                                <div
                                    v-if="
                                        Number(order.discount) > 0 ||
                                        (Number(order.subtotal) > 0 &&
                                            Number(order.subtotal) !==
                                                Number(order.total_price))
                                    "
                                    class="space-y-0.5 text-xs tabular-nums text-muted-foreground"
                                >
                                    <p
                                        v-if="
                                            Number(order.subtotal) > 0 &&
                                            (Number(order.discount) > 0 ||
                                                Number(order.subtotal) !==
                                                    Number(order.total_price))
                                        "
                                    >
                                        {{ t('Subtotal') }}
                                        €{{ Number(order.subtotal).toFixed(2) }}
                                    </p>
                                    <p
                                        v-if="Number(order.discount) > 0"
                                        class="text-destructive"
                                    >
                                        {{ t('Discount') }}
                                        -€{{
                                            Number(order.discount).toFixed(2)
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-if="
                                        order.status === 2 &&
                                        order.type === 1 &&
                                        drivers.length
                                    "
                                    class="pt-1"
                                >
                                    <select
                                        class="h-8 w-full rounded-full border border-border/70 bg-background px-3 text-xs"
                                        :value="
                                            driverDraft[order.id] ??
                                            order.deliveryman_id ??
                                            ''
                                        "
                                        @change="
                                            driverDraft[order.id] =
                                                Number(
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                ) || ''
                                        "
                                    >
                                        <option value="">
                                            {{ t('Select driver') }}
                                        </option>
                                        <option
                                            v-for="driver in drivers"
                                            :key="driver.id"
                                            :value="driver.id"
                                        >
                                            {{ driver.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div
                                class="flex w-[7rem] shrink-0 flex-col items-stretch gap-1.5"
                            >
                                <Can
                                    v-if="order.status === 1"
                                    permission="orders.accept"
                                >
                                    <Button
                                        size="sm"
                                        class="h-8 w-full gap-1 rounded-full px-2"
                                        :disabled="busyId !== null"
                                        @click="acceptOrder(order)"
                                    >
                                        <Check class="size-3.5" />
                                        {{ t('Accept') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-8 w-full gap-1 rounded-full px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :disabled="busyId !== null"
                                        @click="rejectOrder(order)"
                                    >
                                        <X class="size-3.5" />
                                        {{ t('Reject') }}
                                    </Button>
                                </Can>

                                <Can
                                    v-if="order.status === 2 && order.type === 1"
                                    permission="orders.edit"
                                >
                                    <Button
                                        size="sm"
                                        class="h-8 w-full gap-1 rounded-full px-2"
                                        :disabled="busyId !== null"
                                        @click="startDelivery(order)"
                                    >
                                        <Truck class="size-3.5" />
                                        {{ t('Deliver') }}
                                    </Button>
                                </Can>

                                <Can
                                    v-if="
                                        order.status === 2 && order.type === 2
                                    "
                                    permission="orders.edit"
                                >
                                    <Button
                                        size="sm"
                                        class="h-8 w-full gap-1 rounded-full px-2"
                                        :disabled="busyId !== null"
                                        @click="completeOrder(order)"
                                    >
                                        <Check class="size-3.5" />
                                        {{ t('Complete') }}
                                    </Button>
                                </Can>

                                <Can
                                    v-if="order.status === 4"
                                    permission="orders.edit"
                                >
                                    <Button
                                        size="sm"
                                        class="h-8 w-full gap-1 rounded-full px-2"
                                        :disabled="busyId !== null"
                                        @click="completeOrder(order)"
                                    >
                                        <Check class="size-3.5" />
                                        {{ t('Complete') }}
                                    </Button>
                                </Can>

                                <Can permission="orders.print">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-8 w-full gap-1 rounded-full px-2"
                                        as-child
                                    >
                                        <a
                                            :href="`/orders/${order.id}/print`"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <Printer class="size-3.5" />
                                            {{ t('Print') }}
                                        </a>
                                    </Button>
                                </Can>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!filteredOrders.length"
                        class="px-2 py-14 text-center text-sm text-muted-foreground"
                    >
                        {{ t('No orders in this tab.') }}
                    </div>
                </div>

                <div
                    class="shrink-0 border-t border-border/60 px-4 py-3 dark:border-white/10"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-muted-foreground">
                            {{ t('Total') }}
                            ·
                            {{ filteredOrders.length }}
                            {{
                                filteredOrders.length === 1
                                    ? t('order')
                                    : t('orders')
                            }}
                        </span>
                        <span
                            class="text-lg font-bold tabular-nums tracking-tight text-foreground"
                        >
                            €{{ activeTabTotal.toFixed(2) }}
                        </span>
                    </div>
                </div>
            </aside>
        </div>
    </MisPage>
</template>

<style src="@/components/dashboard/dash-card.css"></style>

<style scoped>
.orders-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.orders-scroll::-webkit-scrollbar {
    display: none;
}
</style>
