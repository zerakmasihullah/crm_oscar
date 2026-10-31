<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    CheckCircle2,
    Plus,
    Printer,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import {
    MisDataTable,
    MisPage,
    MisPagination,
    MisSelectField,
    MisStatusBadge,
} from '@/components/mis';
import { mountOrderSound, unmountOrderSound } from '@/composables/useOrderSound';
import { useMisPage } from '@/composables/useMisPage';
import type { Paginated } from '@/lib/format';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';
import type { RowActionItem } from '@/lib/row-actions';

type OrderItemSummary = {
    quantity: number;
    title: string;
    size?: string | null;
};

type OrderRecord = {
    id: number;
    customer_name?: string | null;
    phone_number?: string | null;
    address?: string | null;
    status: number;
    type: number;
    total_price: number | string;
    payment_method?: string | null;
    deliveryman_id?: number | null;
    created_at?: string;
    customer?: { id: number; name: string } | null;
    deliveryman?: { id: number; name: string } | null;
    order_items_count?: number;
    items_summary?: OrderItemSummary[];
};

const props = defineProps<{
    items: Paginated<OrderRecord>;
    filters?: Record<string, string | null | undefined>;
    statusOptions: Record<string, string>;
    typeOptions: Record<string, string>;
    deliverymen: { id: number; name: string }[];
    counts: {
        all: number;
        pending: number;
        delivery: number;
        collection: number;
    };
}>();

const { t, can, viewAction, editAction, deleteAction } = useMisPage();
const search = ref(props.filters?.search ?? '');
const statusFilter = ref(
    props.filters?.status ? String(props.filters.status) : '',
);
const typeFilter = ref(props.filters?.type ? String(props.filters.type) : '');
const paymentFilter = ref(props.filters?.payment_method ?? '');
const busyId = ref<number | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.filters,
    (value) => {
        statusFilter.value = value?.status ? String(value.status) : '';
        typeFilter.value = value?.type ? String(value.type) : '';
        paymentFilter.value = value?.payment_method ?? '';
    },
    { deep: true },
);

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

function applyFilters(extra: Record<string, unknown> = {}) {
    router.get(
        '/orders',
        {
            search: search.value.trim() || undefined,
            status: statusFilter.value || undefined,
            type: typeFilter.value || undefined,
            payment_method: paymentFilter.value || undefined,
            deliveryman_id: props.filters?.deliveryman_id || undefined,
            date_from: props.filters?.date_from || undefined,
            date_to: props.filters?.date_to || undefined,
            ...extra,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch([statusFilter, typeFilter, paymentFilter], () => applyFilters());

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if ((value ?? '').trim() === (props.filters?.search ?? '').trim())
            return;
        applyFilters({ search: value.trim() || undefined });
    }, 300);
});

function placedParts(value?: string | null): { day: string; time: string } {
    if (!value) return { day: '', time: '' };
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return { day: '', time: '' };
    return {
        day: date.toLocaleDateString([], {
            month: 'short',
            day: 'numeric',
        }),
        time: date.toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }),
    };
}

const columns = computed(() => [
    {
        key: 'order',
        label: t('Order'),
        width: '4.5rem',
        sortable: true,
        hideable: false,
    },
    { key: 'when', label: t('Placed'), width: '6.5rem', sortable: true },
    { key: 'items', label: t('Items'), hideable: false },
    {
        key: 'customer',
        label: t('Customer'),
        width: '12rem',
        sortable: true,
        hideable: true,
    },
    {
        key: 'status',
        label: t('Status'),
        width: '7.5rem',
        sortable: true,
        type: 'status' as const,
    },
    {
        key: 'total',
        label: t('Total'),
        width: '5.5rem',
        align: 'end' as const,
        sortable: true,
    },
    {
        key: 'actions',
        label: '',
        width: '12.5rem',
        align: 'end' as const,
        hideable: false,
    },
]);

const rows = computed(() =>
    props.items.data.map((item) => {
        const placed = placedParts(item.created_at);
        const customerName =
            item.customer_name || item.customer?.name || '—';
        return {
            ...item,
            order: item.id,
            when: item.created_at ?? '',
            customer: customerName,
            status: item.status,
            status_label:
                props.statusOptions[String(item.status)] ?? String(item.status),
            type_label:
                props.typeOptions[String(item.type)] ?? String(item.type),
            total: Number(item.total_price),
            when_day: placed.day,
            when_time: placed.time,
            items_summary: item.items_summary ?? [],
        };
    }),
);

function actionsFor(item: OrderRecord): RowActionItem[] {
    const actions: RowActionItem[] = [
        viewAction(`/orders/${item.id}`),
        editAction(`/orders/${item.id}/edit`, 'orders.edit'),
    ];

    if (can('orders.print')) {
        actions.push({
            label: t('Print'),
            icon: Printer,
            href: `/orders/${item.id}/print`,
            openInNewTab: true,
        });
    }

    if (item.status === 1 && can('orders.accept')) {
        actions.push(
            {
                label: t('Accept'),
                icon: CheckCircle2,
                method: 'post',
                href: `/orders/${item.id}/accept`,
                separator: true,
            },
            {
                label: t('Reject'),
                icon: XCircle,
                method: 'post',
                href: `/orders/${item.id}/reject`,
                variant: 'destructive',
                confirm: {
                    title: t('Reject order'),
                    description: t(
                        'Customer will be notified by email and push.',
                    ),
                    confirmLabel: t('Reject'),
                },
            },
        );
    }

    actions.push(
        deleteAction(
            {
                href: `/orders/${item.id}`,
                title: t('Delete order'),
                description: t('Delete order #:id?', {
                    id: String(item.id),
                }),
            },
            'orders.delete',
        ),
    );

    return actions;
}

function acceptOrder(item: OrderRecord) {
    if (busyId.value !== null) return;
    const printTab = window.open('about:blank', '_blank');
    busyId.value = item.id;
    router.post(
        `/orders/${item.id}/accept`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (printTab && !printTab.closed) {
                    printTab.location.href = `/orders/${item.id}/print`;
                    return;
                }
                window.open(`/orders/${item.id}/print`, '_blank');
            },
            onError: () => printTab?.close(),
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

function rejectOrder(item: OrderRecord) {
    if (
        !window.confirm(
            t('Reject this order? The customer will be notified.'),
        )
    ) {
        return;
    }
    if (busyId.value !== null) return;
    busyId.value = item.id;
    router.post(
        `/orders/${item.id}/reject`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

onMounted(() => mountOrderSound());
onUnmounted(() => unmountOrderSound());

defineOptions(resourcePageBreadcrumbs('Orders', '/orders'));
</script>

<template>
    <Head :title="t('Orders')" />

    <MisPage>
        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="rows"
            row-key="id"
            searchable
            filterable
            column-toggle
            :exportable="false"
            :printable="false"
            :search-placeholder="t('Search orders, phone, address…')"
            :empty-label="t('No orders found')"
            :filters-active="
                Boolean(statusFilter || typeFilter || paymentFilter)
            "
            clickable
            :row-class="
                (row) =>
                    Number(row.status) === 1
                        ? 'bg-amber-500/[0.12] hover:bg-amber-500/[0.18] dark:bg-amber-400/10 dark:hover:bg-amber-400/15'
                        : undefined
            "
            @row-click="
                ({ row }) => router.visit(`/orders/${row.id as number}`)
            "
        >
            <template #actions>
                <Can permission="orders.create">
                    <Link
                        href="/orders/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>
            <template #filters>
                <div class="grid gap-3 sm:grid-cols-3">
                    <MisSelectField
                        id="status"
                        v-model="statusFilter"
                        :label="t('Status')"
                        :options="[
                            { value: '', label: t('All') },
                            ...Object.entries(statusOptions).map(
                                ([value, label]) => ({
                                    value,
                                    label,
                                }),
                            ),
                        ]"
                    />
                    <MisSelectField
                        id="type"
                        v-model="typeFilter"
                        :label="t('Type')"
                        :options="[
                            { value: '', label: t('All') },
                            ...Object.entries(typeOptions).map(
                                ([value, label]) => ({
                                    value,
                                    label,
                                }),
                            ),
                        ]"
                    />
                    <MisSelectField
                        id="payment"
                        v-model="paymentFilter"
                        :label="t('Payment')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: 'cash', label: t('Cash') },
                            { value: 'card', label: t('Card') },
                            { value: 'stripe', label: t('Stripe') },
                        ]"
                    />
                </div>
            </template>
            <template #cell-order="{ row }">
                <Link
                    :href="`/orders/${row.id}`"
                    class="font-semibold tabular-nums tracking-tight text-foreground hover:text-primary"
                    @click.stop
                >
                    #{{ row.id }}
                </Link>
            </template>

            <template #cell-when="{ row }">
                <div v-if="row.when_day" class="leading-tight">
                    <p class="text-sm font-medium text-foreground/90">
                        {{ row.when_day }}
                    </p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ row.when_time }}
                    </p>
                </div>
                <span v-else class="text-muted-foreground">—</span>
            </template>

            <template #cell-items="{ row }">
                <div class="min-w-0 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            class="inline-flex shrink-0 rounded-full bg-[var(--brand-accent)] px-2 py-0.5 text-[10px] font-bold tracking-wide text-[#111318] uppercase"
                        >
                            {{ row.type_label }}
                        </span>
                        <span
                            v-if="row.payment_method"
                            class="text-[11px] capitalize text-muted-foreground"
                        >
                            {{ row.payment_method }}
                        </span>
                    </div>
                    <ul
                        v-if="
                            Array.isArray(row.items_summary) &&
                            row.items_summary.length
                        "
                        class="space-y-0.5"
                    >
                        <li
                            v-for="(line, idx) in (
                                row.items_summary as OrderItemSummary[]
                            ).slice(0, 4)"
                            :key="`${row.id}-${idx}`"
                            class="truncate text-sm text-foreground/90"
                        >
                            <span
                                class="me-1 font-semibold tabular-nums text-foreground"
                                >{{ line.quantity }}×</span
                            >
                            {{ line.title
                            }}<span
                                v-if="line.size"
                                class="text-muted-foreground"
                            >
                                · {{ line.size }}</span
                            >
                        </li>
                        <li
                            v-if="
                                (row.items_summary as OrderItemSummary[])
                                    .length > 4
                            "
                            class="text-xs text-muted-foreground"
                        >
                            +{{
                                (row.items_summary as OrderItemSummary[])
                                    .length - 4
                            }}
                            {{ t('more') }}
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">—</p>
                </div>
            </template>

            <template #cell-customer="{ row }">
                <div class="min-w-0 space-y-0.5">
                    <p class="truncate text-sm font-medium text-foreground">
                        {{ row.customer }}
                    </p>
                    <p
                        v-if="row.phone_number"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ row.phone_number }}
                    </p>
                    <p
                        v-if="row.type === 1 && row.address"
                        class="truncate text-xs text-muted-foreground/80"
                    >
                        {{ row.address }}
                    </p>
                </div>
            </template>

            <template #cell-status="{ row }">
                <MisStatusBadge
                    :status="String(row.status_label ?? '')"
                    :label="String(row.status_label ?? '')"
                    :map="statusToneMap"
                />
            </template>

            <template #cell-total="{ row }">
                <span
                    class="block text-sm font-semibold tabular-nums tracking-tight text-foreground"
                >
                    €{{ Number(row.total).toFixed(2) }}
                </span>
            </template>

            <template #cell-actions="{ row }">
                <div
                    data-no-row-click
                    class="flex items-center justify-end gap-1"
                    :class="{ 'opacity-60': busyId === row.id }"
                    @click.stop
                >
                    <Can
                        v-if="row.status === 1"
                        permission="orders.accept"
                    >
                        <Button
                            size="sm"
                            class="h-8 gap-1 rounded-full px-3"
                            :disabled="busyId !== null"
                            @click="acceptOrder(row as OrderRecord)"
                        >
                            <Check class="size-3.5" />
                            {{ t('Accept') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 gap-1 rounded-full px-2.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            :disabled="busyId !== null"
                            :title="t('Reject')"
                            @click="rejectOrder(row as OrderRecord)"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </Can>
                    <Can permission="orders.print">
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            as-child
                        >
                            <a
                                :href="`/orders/${row.id}/print`"
                                target="_blank"
                                rel="noopener"
                                :title="t('Print')"
                            >
                                <Printer class="size-4" />
                            </a>
                        </Button>
                    </Can>
                    <RowActionsMenu :actions="actionsFor(row as OrderRecord)" />
                </div>
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </MisPage>
</template>
