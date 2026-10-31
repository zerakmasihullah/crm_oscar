<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    Clock,
    CreditCard,
    ExternalLink,
    MapPin,
    Phone,
    Printer,
    Truck,
    User,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import Can from '@/components/Can.vue';
import { Button } from '@/components/ui/button';
import {
    MisPage,
    MisPageHeader,
    MisStatusBadge,
} from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';
import { pageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type LineChoice = {
    name: string;
    quantity: number;
    price?: number;
};

type LineItem = {
    id: number;
    menu_item_id?: number | null;
    title: string;
    size?: string | null;
    quantity: number;
    unit_price: number;
    line_total: number;
    choices: LineChoice[];
};

type OrderShow = {
    id: number;
    status: number;
    status_label: string;
    type: number;
    type_label: string;
    source_label: string;
    notes_text: string;
    customer_name?: string | null;
    phone_number?: string | null;
    address?: string | null;
    area_code?: string | null;
    payment_method?: string | null;
    payment_id?: string | null;
    stripe_status?: string | null;
    subtotal?: number | null;
    discount?: number | null;
    total_price?: number | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
    created_at?: string | null;
    delivery_datetime?: string | null;
    customer?: {
        id: number;
        name: string;
        email?: string | null;
        phone_number?: string | null;
    } | null;
    deliveryman?: { id: number; name: string } | null;
    line_items: LineItem[];
};

const props = defineProps<{
    item: OrderShow;
    statusOptions: Record<string, string>;
}>();

const { t } = useTranslations();

defineOptions(
    pageBreadcrumbs(
        { title: 'Orders', href: '/orders' },
        { title: 'View', href: '/orders' },
    ),
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

function money(value?: number | null): string {
    return `€${Number(value ?? 0).toFixed(2)}`;
}

function formatWhen(value?: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString([], {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

const placedAt = computed(() => formatWhen(props.item.created_at));
const dueAt = computed(() => formatWhen(props.item.delivery_datetime));

const customerName = computed(
    () => props.item.customer_name || props.item.customer?.name || null,
);

const phone = computed(
    () => props.item.phone_number || props.item.customer?.phone_number || null,
);

const hasCoords = computed(() => {
    const lat =
        props.item.latitude != null && props.item.latitude !== ''
            ? Number(props.item.latitude)
            : NaN;
    const lng =
        props.item.longitude != null && props.item.longitude !== ''
            ? Number(props.item.longitude)
            : NaN;
    return !Number.isNaN(lat) && !Number.isNaN(lng);
});

const fees = computed(() => {
    const sub = Number(props.item.subtotal ?? 0);
    const disc = Number(props.item.discount ?? 0);
    const total = Number(props.item.total_price ?? 0);
    const value = Math.round((total - (sub - disc)) * 100) / 100;
    return value > 0.001 ? value : 0;
});

const stripeUrl = computed(() => {
    const id = props.item.payment_id?.trim();
    if (!id) return null;
    return `https://dashboard.stripe.com/payments/${id}`;
});

const mapUrl = computed(() => {
    if (hasCoords.value) {
        return `https://www.google.com/maps?q=${props.item.latitude},${props.item.longitude}`;
    }
    if (props.item.address?.trim()) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.item.address.trim())}`;
    }
    return null;
});

const timeLabel = computed(() =>
    props.item.type === 1 ? t('Delivery time') : t('Collection time'),
);

function accept() {
    const printTab = window.open('about:blank', '_blank');
    router.post(
        `/orders/${props.item.id}/accept`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (printTab && !printTab.closed) {
                    printTab.location.href = `/orders/${props.item.id}/print`;
                    return;
                }
                window.open(`/orders/${props.item.id}/print`, '_blank');
            },
            onError: () => printTab?.close(),
        },
    );
}

function reject() {
    router.post(`/orders/${props.item.id}/reject`);
}
</script>

<template>
    <Head :title="`${t('Order')} #${item.id}`" />

    <MisPage>
        <MisPageHeader :title="`${t('Order')} #${item.id}`" class="!gap-3">
            <template #description>
                <div class="flex flex-wrap items-center gap-1.5">
                    <MisStatusBadge
                        :status="item.status_label"
                        :label="item.status_label"
                        :map="statusToneMap"
                    />
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-[var(--brand-accent)]/20 px-2.5 py-0.5 text-xs font-semibold text-foreground"
                    >
                        <Truck v-if="item.type === 1" class="size-3" />
                        {{ item.type_label }}
                    </span>
                    <span
                        v-if="item.source_label"
                        class="text-xs text-muted-foreground"
                    >
                        {{ item.source_label }}
                    </span>
                </div>
            </template>
            <template #actions>
                <Can v-if="item.status === 1" permission="orders.accept">
                    <Button size="sm" class="h-8 gap-1.5 px-3" @click="accept">
                        <Check class="size-3.5" />
                        {{ t('Accept') }}
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 gap-1.5 px-3 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="reject"
                    >
                        <X class="size-3.5" />
                        {{ t('Reject') }}
                    </Button>
                </Can>
                <Can permission="orders.print">
                    <Button size="sm" variant="outline" class="h-8 gap-1.5 px-3" as-child>
                        <a :href="`/orders/${item.id}/print`" target="_blank">
                            <Printer class="size-3.5" />
                            {{ t('Print') }}
                        </a>
                    </Button>
                </Can>
            </template>
        </MisPageHeader>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <section class="min-w-0 space-y-4">
                <!-- Meta strip -->
                <div
                    class="grid grid-cols-2 gap-3 rounded-2xl border border-border/60 bg-muted/30 p-3 sm:grid-cols-4"
                >
                    <div class="rounded-xl bg-card px-3 py-2.5 shadow-sm shadow-black/[0.02]">
                        <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {{ t('Placed') }}
                        </p>
                        <p class="mt-1 text-[13px] font-medium leading-snug">
                            {{ placedAt || '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-card px-3 py-2.5 shadow-sm shadow-black/[0.02]">
                        <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {{ timeLabel }}
                        </p>
                        <p class="mt-1 flex items-start gap-1 text-[13px] font-medium leading-snug">
                            <Clock class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                            {{ dueAt || '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-card px-3 py-2.5 shadow-sm shadow-black/[0.02]">
                        <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {{ t('Payment') }}
                        </p>
                        <p class="mt-1 text-[13px] font-medium capitalize leading-snug">
                            {{ item.payment_method || '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-card px-3 py-2.5 shadow-sm shadow-black/[0.02]">
                        <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {{ t('Total') }}
                        </p>
                        <p class="mt-1 text-[15px] font-bold tabular-nums tracking-tight">
                            {{ money(item.total_price) }}
                        </p>
                    </div>
                </div>

                <!-- Items -->
                <div class="overflow-hidden rounded-2xl border border-border/60 bg-card">
                    <div
                        class="flex items-center justify-between px-4 py-3"
                    >
                        <h2 class="text-sm font-semibold tracking-tight">
                            {{ t('Items') }}
                        </h2>
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold tabular-nums text-muted-foreground"
                        >
                            {{ item.line_items.length }}
                            {{
                                item.line_items.length === 1
                                    ? t('item')
                                    : t('items')
                            }}
                        </span>
                    </div>

                    <ul v-if="item.line_items.length" class="border-t border-border/50">
                        <li
                            v-for="line in item.line_items"
                            :key="line.id"
                            class="flex items-start gap-3 border-b border-border/40 px-4 py-3.5 last:border-b-0"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand-accent)]/25 text-xs font-bold tabular-nums text-foreground"
                            >
                                {{ line.quantity }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold leading-snug">
                                            {{ line.title }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            <span v-if="line.size">{{ line.size }} · </span>
                                            <span class="tabular-nums">{{ money(line.unit_price) }}</span>
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold tabular-nums">
                                        {{ money(line.line_total) }}
                                    </p>
                                </div>
                                <ul
                                    v-if="line.choices.length"
                                    class="mt-2 space-y-1 border-l-2 border-border/60 pl-2.5"
                                >
                                    <li
                                        v-for="(choice, idx) in line.choices"
                                        :key="`${line.id}-${idx}`"
                                        class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                                    >
                                        <span>
                                            {{ choice.quantity }}× {{ choice.name }}
                                        </span>
                                        <span
                                            v-if="Number(choice.price) > 0"
                                            class="tabular-nums"
                                        >
                                            {{ money(choice.price) }}
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="border-t border-border/50 px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        {{ t('No items') }}
                    </p>

                    <div class="space-y-2 border-t border-border/50 bg-muted/25 px-4 py-3.5">
                        <div
                            class="flex items-center justify-between text-sm text-muted-foreground"
                        >
                            <span>{{ t('Subtotal') }}</span>
                            <span class="tabular-nums">{{ money(item.subtotal) }}</span>
                        </div>
                        <div
                            v-if="Number(item.discount) > 0"
                            class="flex items-center justify-between text-sm text-muted-foreground"
                        >
                            <span>{{ t('Discount') }}</span>
                            <span class="tabular-nums">−{{ money(item.discount) }}</span>
                        </div>
                        <div
                            v-if="fees > 0"
                            class="flex items-center justify-between text-sm text-muted-foreground"
                        >
                            <span>{{ t('Fees') }}</span>
                            <span class="tabular-nums">{{ money(fees) }}</span>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-border/40 pt-2.5"
                        >
                            <span class="text-sm font-semibold">{{ t('Total') }}</span>
                            <span class="text-lg font-bold tabular-nums tracking-tight">
                                {{ money(item.total_price) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="item.notes_text"
                    class="rounded-2xl border border-border/60 bg-card px-4 py-3.5"
                >
                    <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {{ t('Order Notes') }}
                    </p>
                    <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed">
                        {{ item.notes_text }}
                    </p>
                </div>
            </section>

            <aside class="space-y-3 lg:sticky lg:top-3 lg:self-start">
                <div class="rounded-2xl border border-border/60 bg-card p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-muted"
                        >
                            <User class="size-3.5 text-muted-foreground" />
                        </span>
                        <h2 class="text-sm font-semibold">{{ t('Customer') }}</h2>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Name') }}
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                <Link
                                    v-if="item.customer?.id"
                                    :href="`/oscar-users/${item.customer.id}`"
                                    class="text-primary hover:underline"
                                >
                                    {{ customerName }}
                                </Link>
                                <span v-else>{{ customerName || '—' }}</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Phone') }}
                            </p>
                            <a
                                v-if="phone"
                                :href="`tel:${phone}`"
                                class="mt-0.5 inline-flex items-center gap-1.5 text-sm hover:text-foreground"
                            >
                                <Phone class="size-3.5 text-muted-foreground" />
                                {{ phone }}
                            </a>
                            <p v-else class="mt-0.5 text-sm">—</p>
                        </div>
                        <div v-if="item.customer?.email">
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Email') }}
                            </p>
                            <p class="mt-0.5 truncate text-sm">{{ item.customer.email }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Address') }}
                            </p>
                            <p class="mt-0.5 text-sm leading-relaxed">
                                {{ item.address?.trim() || '—' }}
                            </p>
                        </div>
                        <div v-if="item.area_code?.trim()">
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Area code') }}
                            </p>
                            <p class="mt-0.5 text-sm">{{ item.area_code }}</p>
                        </div>
                        <div v-if="item.deliveryman?.name">
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Driver') }}
                            </p>
                            <p class="mt-0.5 text-sm font-medium">{{ item.deliveryman.name }}</p>
                        </div>
                        <a
                            v-if="mapUrl"
                            :href="mapUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                        >
                            <MapPin class="size-3.5" />
                            {{ t('Open map') }}
                            <ExternalLink class="size-3" />
                        </a>
                        <p
                            v-if="hasCoords"
                            class="text-[11px] tabular-nums text-muted-foreground"
                        >
                            {{ item.latitude }}, {{ item.longitude }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="item.payment_id || item.stripe_status"
                    class="rounded-2xl border border-border/60 bg-card p-4"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-muted"
                        >
                            <CreditCard class="size-3.5 text-muted-foreground" />
                        </span>
                        <h2 class="text-sm font-semibold">{{ t('Payment') }}</h2>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Method') }}
                            </p>
                            <p class="mt-0.5 font-medium capitalize">
                                {{ item.payment_method || '—' }}
                            </p>
                        </div>
                        <div v-if="item.payment_id">
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Stripe Payment ID') }}
                            </p>
                            <a
                                v-if="stripeUrl"
                                :href="stripeUrl"
                                target="_blank"
                                rel="noopener"
                                class="mt-0.5 inline-flex items-center gap-1 break-all text-xs text-primary hover:underline"
                            >
                                {{ item.payment_id }}
                                <ExternalLink class="size-3 shrink-0" />
                            </a>
                        </div>
                        <div v-if="item.stripe_status">
                            <p class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ t('Payment Status') }}
                            </p>
                            <p class="mt-0.5 capitalize">{{ item.stripe_status }}</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </MisPage>
</template>
