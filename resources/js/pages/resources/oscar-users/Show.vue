<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageCircle, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Can from '@/components/Can.vue';
import {
    MisDataTable,
    MisDetailGrid,
    MisPage,
    MisPagination,
    MisSection,
    MisStatCard,
    MisStatGrid,
    type MisDataTableColumn,
} from '@/components/mis';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';

type OrderRow = {
    id: number;
    status_label: string;
    type_label: string;
    source_label: string;
    total_price: number;
    payment_method?: string | null;
    phone_number?: string | null;
    address?: string | null;
    deliveryman?: string | null;
    items_summary?: string;
    created_at?: string | null;
};

type Stats = {
    orders_count: number;
    completed_count: number;
    pending_count: number;
    rejected_count: number;
    total_spent: number;
    avg_order: number;
    first_order_at?: string | null;
    last_order_at?: string | null;
    source_counts: Record<string, number>;
    top_source: string;
    top_source_label: string;
    top_source_share: number;
    type_counts: { delivery: number; collection: number };
    status_counts: Record<string, number>;
    payment_counts: Record<string, number>;
    top_addresses: { address: string; count: number }[];
    top_items: { title: string; sold: number; revenue: number }[];
    has_push: boolean;
};

const props = defineProps<{
    item: {
        id: number;
        name: string;
        email: string;
        phone_number?: string | null;
        is_active: boolean;
        area_code?: string | null;
        latitude?: string | number | null;
        longitude?: string | number | null;
        email_verified_at?: string | null;
        created_at?: string | null;
        updated_at?: string | null;
        fcm_token?: boolean;
    };
    stats: Stats;
    orders: Paginated<OrderRow>;
}>();

const { t } = useTranslations();

function fmtDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

const profileDetails = computed(() => [
    { label: t('Name'), value: props.item.name },
    { label: t('Email'), value: props.item.email },
    { label: t('Phone'), value: props.item.phone_number || '—' },
    { label: t('Area code'), value: props.item.area_code || '—' },
    {
        label: t('Location'),
        value:
            props.item.latitude && props.item.longitude
                ? `${props.item.latitude}, ${props.item.longitude}`
                : '—',
    },
    {
        label: t('Email verified'),
        value: props.item.email_verified_at
            ? fmtDate(props.item.email_verified_at)
            : t('No'),
    },
    {
        label: t('Push notifications'),
        value: props.stats.has_push ? t('Enabled') : t('Not registered'),
    },
    { label: t('Joined'), value: fmtDate(props.item.created_at) },
    { label: t('Updated'), value: fmtDate(props.item.updated_at) },
    {
        label: t('First order'),
        value: fmtDate(props.stats.first_order_at),
    },
    {
        label: t('Last order'),
        value: fmtDate(props.stats.last_order_at),
    },
]);

const sourceRows = computed(() =>
    Object.entries(props.stats.source_counts).map(([key, count]) => ({
        key,
        label:
            key === 'mobile'
                ? t('Mobile App')
                : key === 'admin'
                  ? t('Admin')
                  : t('Website'),
        count,
        share:
            props.stats.orders_count > 0
                ? Math.round((count / props.stats.orders_count) * 100)
                : 0,
    })),
);

const columns = computed<MisDataTableColumn[]>(() => [
    { key: 'id', label: '#' },
    { key: 'status_label', label: t('Status') },
    { key: 'type_label', label: t('Type') },
    { key: 'source_label', label: t('Source') },
    { key: 'total_price', label: t('Total'), align: 'end' },
    { key: 'payment_method', label: t('Payment') },
    { key: 'deliveryman', label: t('Driver') },
    { key: 'items_summary', label: t('Items') },
    { key: 'created_at', label: t('Created') },
]);

const orderRows = computed(() =>
    props.orders.data.map((order) => ({
        ...order,
        total_price: `€${Number(order.total_price).toFixed(2)}`,
        payment_method: order.payment_method || '—',
        deliveryman: order.deliveryman || '—',
        items_summary: order.items_summary || '—',
        created_at: order.created_at
            ? new Date(order.created_at).toLocaleString()
            : '—',
    })),
);

function destroy(): void {
    if (!confirm(t('This action cannot be undone.'))) return;
    router.delete(`/oscar-users/${props.item.id}`);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: '/oscar-users' },
            { title: 'Details', href: '/oscar-users' },
        ],
    },
});
</script>

<template>
    <Head :title="item.name" />

    <MisPage>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-xl font-semibold tracking-tight">
                        {{ item.name }}
                    </h1>
                    <Badge :variant="item.is_active ? 'default' : 'outline'">
                        {{ item.is_active ? t('Active') : t('Inactive') }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ item.email }}
                    <span v-if="item.phone_number"> · {{ item.phone_number }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Can permission="chats.view">
                    <Button as-child variant="outline">
                        <Link :href="`/chats?user=${item.id}`">
                            <MessageCircle class="size-4" />
                            {{ t('Chat') }}
                        </Link>
                    </Button>
                </Can>
                <Can permission="oscar_users.edit">
                    <Button as-child variant="outline">
                        <Link :href="`/oscar-users/${item.id}/edit`">
                            <Pencil class="size-4" />
                            {{ t('Edit') }}
                        </Link>
                    </Button>
                </Can>
                <Can permission="oscar_users.delete">
                    <Button variant="destructive" @click="destroy">
                        <Trash2 class="size-4" />
                        {{ t('Delete') }}
                    </Button>
                </Can>
            </div>
        </div>

        <MisStatGrid>
            <MisStatCard
                :label="t('Orders')"
                :value="String(stats.orders_count)"
            />
            <MisStatCard
                :label="t('Total spent')"
                :value="`€${Number(stats.total_spent).toFixed(2)}`"
            />
            <MisStatCard
                :label="t('Average order')"
                :value="`€${Number(stats.avg_order).toFixed(2)}`"
            />
            <MisStatCard
                :label="t('Most orders from')"
                :value="stats.top_source_label"
                :hint="`${stats.top_source_share}% ${t('of orders')}`"
                tone="info"
            />
        </MisStatGrid>

        <div class="grid gap-6 lg:grid-cols-2">
            <MisSection :title="t('Customer profile')">
                <MisDetailGrid :items="profileDetails" :columns="2" />
            </MisSection>

            <MisSection :title="t('Order sources')">
                <p class="mb-3 text-sm text-muted-foreground">
                    {{ t('Where this customer places most of their orders.') }}
                </p>
                <ul class="space-y-2">
                    <li
                        v-for="row in sourceRows"
                        :key="row.key"
                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                    >
                        <span class="font-medium">{{ row.label }}</span>
                        <span class="text-muted-foreground">
                            {{ row.count }} ({{ row.share }}%)
                        </span>
                    </li>
                    <li
                        v-if="!sourceRows.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('No orders yet.') }}
                    </li>
                </ul>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <div class="rounded-lg border px-3 py-2 text-sm">
                        <p class="text-muted-foreground">{{ t('Delivery') }}</p>
                        <p class="font-semibold">{{ stats.type_counts.delivery }}</p>
                    </div>
                    <div class="rounded-lg border px-3 py-2 text-sm">
                        <p class="text-muted-foreground">{{ t('Collection') }}</p>
                        <p class="font-semibold">
                            {{ stats.type_counts.collection }}
                        </p>
                    </div>
                    <div class="rounded-lg border px-3 py-2 text-sm">
                        <p class="text-muted-foreground">{{ t('Completed') }}</p>
                        <p class="font-semibold">{{ stats.completed_count }}</p>
                    </div>
                    <div class="rounded-lg border px-3 py-2 text-sm">
                        <p class="text-muted-foreground">{{ t('Pending') }}</p>
                        <p class="font-semibold">{{ stats.pending_count }}</p>
                    </div>
                </div>
            </MisSection>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <MisSection :title="t('Favorite items')">
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="itemRow in stats.top_items"
                        :key="itemRow.title"
                        class="flex items-center justify-between px-4 py-3 text-sm"
                    >
                        <span>{{ itemRow.title }}</span>
                        <span class="text-muted-foreground">
                            {{ itemRow.sold }} · €{{
                                Number(itemRow.revenue).toFixed(2)
                            }}
                        </span>
                    </li>
                    <li
                        v-if="!stats.top_items.length"
                        class="px-4 py-6 text-sm text-muted-foreground"
                    >
                        {{ t('No items yet.') }}
                    </li>
                </ul>
            </MisSection>

            <MisSection :title="t('Top addresses')">
                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="row in stats.top_addresses"
                        :key="row.address"
                        class="flex items-start justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <span class="min-w-0 break-words">{{ row.address }}</span>
                        <span class="shrink-0 text-muted-foreground">
                            ×{{ row.count }}
                        </span>
                    </li>
                    <li
                        v-if="!stats.top_addresses.length"
                        class="px-4 py-6 text-sm text-muted-foreground"
                    >
                        {{ t('No addresses yet.') }}
                    </li>
                </ul>

                <div v-if="Object.keys(stats.payment_counts).length" class="mt-4">
                    <p class="mb-2 text-sm font-medium">{{ t('Payments') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="(count, method) in stats.payment_counts"
                            :key="method"
                            variant="secondary"
                        >
                            {{ method }} · {{ count }}
                        </Badge>
                    </div>
                </div>
            </MisSection>
        </div>

        <MisSection :title="t('Orders')">
            <MisDataTable
                :columns="columns"
                :rows="orderRows"
                row-key="id"
                :empty-label="t('No orders yet.')"
                clickable
                @row-click="(row) => router.visit(`/orders/${row.id as number}`)"
            >
                <template #footer>
                    <MisPagination :pagination="orders" />
                </template>
            </MisDataTable>
        </MisSection>
    </MisPage>
</template>
