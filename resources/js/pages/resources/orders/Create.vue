<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Check,
    CreditCard,
    Minus,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { MisPage } from '@/components/mis';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';
import { cn } from '@/lib/utils';

const DEFAULT_IMAGE = '/brand/menu-default.jpg';
const CART_STORAGE_KEY = 'oscar.orders.create.cart.v1';

type Size = { id: number; size: string; price: number };
type Attachment = { id: number; name: string; price: number };
type Topping = {
    id: number;
    name: string;
    is_optional: boolean;
    required: number;
    attachments: Attachment[];
};
type MenuItemOpt = {
    id: number;
    title: string;
    price: number;
    image_url?: string | null;
    is_deal?: boolean;
    sizes: Size[];
    toppings: Topping[];
};
type MenuOpt = { id: number; title: string; items: MenuItemOpt[] };

type ToppingSelection = Record<number, Record<number, number>>;

type CartLine = {
    key: string;
    menu_item_id: number;
    size_id: number | null;
    title: string;
    size_label: string | null;
    quantity: number;
    unit_price: number;
    toppings: ToppingSelection;
    extras_label: string;
    is_deal: boolean;
};

type DeliverySettings = {
    delivery_price?: number | string | null;
    free_delivery_limit?: number | string | null;
    service_fees?: number | string | null;
    discount_for_all?: number | string | null;
    min_order?: number | string | null;
};

const props = defineProps<{
    menus: MenuOpt[];
    customers: {
        id: number;
        name: string;
        email?: string;
        phone_number?: string;
    }[];
    deliverymen: { id: number; name: string }[];
    statusOptions: Record<string, string>;
    typeOptions: Record<string, string>;
    delivery?: DeliverySettings | null;
}>();

const { t } = useTranslations();

const search = ref('');
const activeMenuId = ref<number | 'all' | 'deals'>('all');
const cart = ref<CartLine[]>([]);
const showCustomer = ref(false);
const discountPercent = ref(0);
const configError = ref('');

const discountOptions = [0, 5, 10, 15, 20] as const;

const configuring = ref<MenuItemOpt | null>(null);
const selectedSizeId = ref<number | null>(null);
const selectedToppings = ref<ToppingSelection>({});

const form = useForm({
    customer_name: '',
    phone_number: '',
    address: '',
    status: '2', // Accepted
    type: '2',
    discount: 0,
    payment_method: 'cash',
    deliveryman_id: '',
    description: '',
});

const flatItems = computed(() =>
    props.menus.flatMap((menu) =>
        menu.items.map((item) => ({
            ...item,
            menu_id: menu.id,
            menu_title: menu.title,
            is_deal: item.is_deal ?? (item.toppings?.length ?? 0) > 0,
            toppings: item.toppings ?? [],
            sizes: item.sizes ?? [],
        })),
    ),
);

const dealCount = computed(
    () => flatItems.value.filter((item) => item.is_deal).length,
);

const categoryChips = computed(() => {
    const chips: {
        id: number | 'all' | 'deals';
        title: string;
        count: number;
        image: string;
    }[] = [
        {
            id: 'all',
            title: t('All'),
            count: flatItems.value.length,
            image: flatItems.value[0]
                ? itemImage(flatItems.value[0])
                : DEFAULT_IMAGE,
        },
    ];

    if (dealCount.value) {
        const firstDeal = flatItems.value.find((item) => item.is_deal);
        chips.push({
            id: 'deals',
            title: t('Deals'),
            count: dealCount.value,
            image: firstDeal ? itemImage(firstDeal) : DEFAULT_IMAGE,
        });
    }

    for (const menu of props.menus) {
        chips.push({
            id: menu.id,
            title: menu.title,
            count: menu.items.length,
            image: menu.items[0] ? itemImage(menu.items[0]) : DEFAULT_IMAGE,
        });
    }

    return chips;
});

const page = usePage<{
    flash?: { print_order_id?: number | string | null };
}>();

const visibleItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    return flatItems.value.filter((item) => {
        if (activeMenuId.value === 'deals' && !item.is_deal) return false;
        if (
            activeMenuId.value !== 'all' &&
            activeMenuId.value !== 'deals' &&
            item.menu_id !== activeMenuId.value
        ) {
            return false;
        }
        if (!q) return true;
        return item.title.toLowerCase().includes(q);
    });
});

const cartCount = computed(() =>
    cart.value.reduce((sum, line) => sum + line.quantity, 0),
);

const cartSubtotal = computed(() =>
    cart.value.reduce((sum, line) => sum + line.unit_price * line.quantity, 0),
);

const discountAmount = computed(() =>
    Math.round(
        ((cartSubtotal.value * Number(discountPercent.value || 0)) / 100) * 100,
    ) / 100,
);

const serviceFee = computed(() =>
    Number(props.delivery?.service_fees ?? 0),
);

const storeDiscount = computed(() =>
    Number(props.delivery?.discount_for_all ?? 0),
);

const minOrder = computed(() => Number(props.delivery?.min_order ?? 0));

const freeDeliveryLimit = computed(() =>
    Number(props.delivery?.free_delivery_limit ?? 0),
);

const deliveryFee = computed(() => {
    if (Number(form.type) !== 1) return 0;
    const price = Number(props.delivery?.delivery_price ?? 0);
    if (
        freeDeliveryLimit.value > 0 &&
        cartSubtotal.value >= freeDeliveryLimit.value
    ) {
        return 0;
    }
    return price;
});

const deliveryIsFree = computed(
    () =>
        Number(form.type) === 1 &&
        freeDeliveryLimit.value > 0 &&
        cartSubtotal.value >= freeDeliveryLimit.value,
);

const belowMinOrder = computed(
    () =>
        minOrder.value > 0 &&
        cartSubtotal.value > 0 &&
        cartSubtotal.value < minOrder.value,
);

const cartTotal = computed(() =>
    Math.max(
        0,
        cartSubtotal.value +
            serviceFee.value +
            deliveryFee.value -
            discountAmount.value -
            storeDiscount.value,
    ),
);

watch(
    discountAmount,
    (value) => {
        form.discount = value;
    },
    { immediate: true },
);

const configBasePrice = computed(() => {
    const item = configuring.value;
    if (!item) return 0;
    if (item.sizes.length) {
        const size = item.sizes.find((s) => s.id === selectedSizeId.value);
        return Number(size?.price ?? 0);
    }
    return Number(item.price);
});

const configExtras = computed(() => {
    const item = configuring.value;
    if (!item) return 0;
    let extra = 0;
    for (const topping of item.toppings) {
        for (const attachment of topping.attachments) {
            const qty = Number(
                selectedToppings.value[topping.id]?.[attachment.id] ?? 0,
            );
            extra += Number(attachment.price) * qty;
        }
    }
    return extra;
});

const configUnitPrice = computed(
    () => configBasePrice.value + configExtras.value,
);

function itemNeedsConfig(item: MenuItemOpt): boolean {
    return (item.sizes?.length ?? 0) > 0 || (item.toppings?.length ?? 0) > 0;
}

function itemPriceLabel(item: MenuItemOpt): string {
    if (item.sizes?.length) {
        const prices = item.sizes.map((s) => s.price);
        const min = Math.min(...prices);
        const max = Math.max(...prices);
        return min === max ? `€${min.toFixed(2)}` : `€${min.toFixed(2)}+`;
    }
    return `€${Number(item.price).toFixed(2)}`;
}

function itemImage(item: {
    image_url?: string | null;
}): string {
    const src = item.image_url?.trim();
    if (!src) return DEFAULT_IMAGE;

    // Never use remote/CDN images on this screen — local assets only.
    if (
        src.startsWith('http://') ||
        src.startsWith('https://') ||
        src.startsWith('//') ||
        src.startsWith('/storage/http')
    ) {
        return DEFAULT_IMAGE;
    }

    if (src.startsWith('/brand/') || src.startsWith('/storage/') || src.startsWith('/images/')) {
        return src;
    }

    // Relative public-disk path
    if (!src.includes('://')) {
        return src.startsWith('/') ? src : `/storage/${src.replace(/^\/+/, '')}`;
    }

    return DEFAULT_IMAGE;
}

function restoreDraft() {
    try {
        const raw = localStorage.getItem(CART_STORAGE_KEY);
        if (!raw) return;
        const data = JSON.parse(raw) as {
            cart?: CartLine[];
            form?: Partial<typeof form>;
            activeMenuId?: number | 'all' | 'deals';
            showCustomer?: boolean;
            discountPercent?: number;
        };
        if (Array.isArray(data.cart)) {
            cart.value = data.cart;
        }
        if (data.form) {
            if (typeof data.form.customer_name === 'string') {
                form.customer_name = data.form.customer_name;
            }
            if (typeof data.form.phone_number === 'string') {
                form.phone_number = data.form.phone_number;
            }
            if (typeof data.form.address === 'string') {
                form.address = data.form.address;
            }
            if (data.form.type === '1' || data.form.type === '2') {
                form.type = data.form.type;
            }
            if (
                data.form.payment_method === 'cash' ||
                data.form.payment_method === 'card' ||
                data.form.payment_method === 'stripe'
            ) {
                form.payment_method = data.form.payment_method;
            }
            if (typeof data.form.description === 'string') {
                form.description = data.form.description;
            }
        }
        if (
            data.activeMenuId === 'all' ||
            data.activeMenuId === 'deals' ||
            typeof data.activeMenuId === 'number'
        ) {
            activeMenuId.value = data.activeMenuId;
        }
        if (typeof data.showCustomer === 'boolean') {
            showCustomer.value = data.showCustomer;
        }
        if (
            typeof data.discountPercent === 'number' &&
            discountOptions.includes(
                data.discountPercent as (typeof discountOptions)[number],
            )
        ) {
            discountPercent.value = data.discountPercent;
        }
    } catch {
        /* ignore corrupt draft */
    }
}

function persistDraft() {
    try {
        localStorage.setItem(
            CART_STORAGE_KEY,
            JSON.stringify({
                cart: cart.value,
                form: {
                    customer_name: form.customer_name,
                    phone_number: form.phone_number,
                    address: form.address,
                    type: form.type,
                    payment_method: form.payment_method,
                    description: form.description,
                },
                activeMenuId: activeMenuId.value,
                showCustomer: showCustomer.value,
                discountPercent: discountPercent.value,
            }),
        );
    } catch {
        /* ignore quota */
    }
}

function clearDraft() {
    try {
        localStorage.removeItem(CART_STORAGE_KEY);
    } catch {
        /* ignore */
    }
}

function emptyToppings(item: MenuItemOpt): ToppingSelection {
    const next: ToppingSelection = {};
    for (const topping of item.toppings ?? []) {
        next[topping.id] = {};
        for (const attachment of topping.attachments) {
            next[topping.id][attachment.id] = 0;
        }
    }
    return next;
}

function openConfig(item: MenuItemOpt) {
    configuring.value = item;
    configError.value = '';
    selectedSizeId.value = item.sizes.length === 1 ? item.sizes[0].id : null;
    selectedToppings.value = emptyToppings(item);
}

function closeConfig() {
    configuring.value = null;
    configError.value = '';
}

function addItem(item: MenuItemOpt) {
    if (itemNeedsConfig(item)) {
        openConfig(item);
        return;
    }
    pushCartLine({
        menu_item_id: item.id,
        size_id: null,
        title: item.title,
        size_label: null,
        unit_price: Number(item.price),
        toppings: {},
        extras_label: '',
        is_deal: Boolean(item.is_deal),
    });
}

function toppingById(toppingId: number): Topping | undefined {
    return configuring.value?.toppings.find(
        (topping) => topping.id === toppingId,
    );
}

function toppingMax(topping: Topping): number | null {
    if (topping.is_optional) return null;
    return Math.max(topping.required || 1, 1);
}

function toppingSelectedCount(toppingId: number): number {
    return Object.values(selectedToppings.value[toppingId] ?? {}).reduce(
        (sum, qty) => sum + Number(qty || 0),
        0,
    );
}

function setToppingQty(toppingId: number, attachmentId: number, qty: number) {
    const topping = toppingById(toppingId);
    if (!topping) return;

    if (!selectedToppings.value[toppingId]) {
        selectedToppings.value[toppingId] = {};
    }

    const nextQty = Math.max(0, qty);
    const current = Number(
        selectedToppings.value[toppingId][attachmentId] ?? 0,
    );
    const others = toppingSelectedCount(toppingId) - current;
    const max = toppingMax(topping);

    selectedToppings.value[toppingId][attachmentId] =
        max === null ? nextQty : Math.min(nextQty, Math.max(0, max - others));
}

function bumpTopping(toppingId: number, attachmentId: number, delta: number) {
    const current = Number(
        selectedToppings.value[toppingId]?.[attachmentId] ?? 0,
    );
    setToppingQty(toppingId, attachmentId, current + delta);
}

function toggleTopping(toppingId: number, attachmentId: number) {
    const topping = toppingById(toppingId);
    if (!topping) return;

    const current = Number(
        selectedToppings.value[toppingId]?.[attachmentId] ?? 0,
    );
    if (current > 0) {
        setToppingQty(toppingId, attachmentId, 0);
        return;
    }

    const max = toppingMax(topping);
    if (max !== null && toppingSelectedCount(toppingId) >= max) return;

    setToppingQty(toppingId, attachmentId, 1);
}

function extrasLabel(item: MenuItemOpt, toppings: ToppingSelection): string {
    const parts: string[] = [];
    for (const topping of item.toppings ?? []) {
        for (const attachment of topping.attachments) {
            const qty = Number(toppings[topping.id]?.[attachment.id] ?? 0);
            if (qty > 0) {
                parts.push(
                    qty > 1 ? `${qty}× ${attachment.name}` : attachment.name,
                );
            }
        }
    }
    return parts.join(', ');
}

function validateConfig(item: MenuItemOpt): string | null {
    if (item.sizes.length && !selectedSizeId.value) {
        return t('Choose a size');
    }

    for (const topping of item.toppings ?? []) {
        if (topping.is_optional) continue;
        const selected = toppingSelectedCount(topping.id);
        if (selected < (topping.required || 1)) {
            return t('Select at least :n for :name.', {
                n: topping.required || 1,
                name: topping.name,
            });
        }
    }

    return null;
}

function confirmConfig() {
    const item = configuring.value;
    if (!item) return;

    const error = validateConfig(item);
    if (error) {
        configError.value = error;
        return;
    }

    const size = item.sizes.find((s) => s.id === selectedSizeId.value) ?? null;
    const toppings = JSON.parse(
        JSON.stringify(selectedToppings.value),
    ) as ToppingSelection;

    pushCartLine({
        menu_item_id: item.id,
        size_id: size?.id ?? null,
        title: item.title,
        size_label: size?.size ?? null,
        unit_price: Number(configUnitPrice.value.toFixed(2)),
        toppings,
        extras_label: extrasLabel(item, toppings),
        is_deal: Boolean(item.is_deal),
    });

    closeConfig();
}

function pushCartLine(line: Omit<CartLine, 'key' | 'quantity'>) {
    const key = JSON.stringify({
        menu_item_id: line.menu_item_id,
        size_id: line.size_id,
        toppings: line.toppings,
    });

    const existing = cart.value.find((row) => row.key === key);
    if (existing) {
        existing.quantity += 1;
        return;
    }

    cart.value.push({
        ...line,
        key,
        quantity: 1,
    });
}

function setQty(key: string, quantity: number) {
    const line = cart.value.find((row) => row.key === key);
    if (!line) return;
    if (quantity <= 0) {
        cart.value = cart.value.filter((row) => row.key !== key);
        return;
    }
    line.quantity = quantity;
}

function removeLine(key: string) {
    cart.value = cart.value.filter((row) => row.key !== key);
}

function clearCart() {
    if (!cart.value.length) return;
    if (!window.confirm(t('Clear the cart?'))) return;
    cart.value = [];
    persistDraft();
}

watch(
    () => [
        cart.value,
        form.customer_name,
        form.phone_number,
        form.address,
        form.type,
        form.payment_method,
        form.description,
        activeMenuId.value,
        showCustomer.value,
        discountPercent.value,
    ],
    () => persistDraft(),
    { deep: true },
);

function resetAfterOrder() {
    cart.value = [];
    discountPercent.value = 0;
    showCustomer.value = false;
    form.customer_name = '';
    form.phone_number = '';
    form.address = '';
    form.description = '';
    form.discount = 0;
    clearDraft();
}

function openPrintTab(orderId: number | string) {
    window.open(`/orders/${orderId}/print`, '_blank', 'noopener');
}

onMounted(() => {
    const orderId = page.props.flash?.print_order_id;
    if (orderId != null && orderId !== '') {
        resetAfterOrder();
        openPrintTab(orderId);
        return;
    }
    restoreDraft();
});

function submit() {
    if (!cart.value.length) return;

    const printTab = window.open('about:blank', '_blank');

    form
        .transform((data) => ({
            customer_id: null,
            customer_name: data.customer_name || null,
            phone_number: data.phone_number || null,
            address: data.type === '1' ? data.address || null : null,
            status: 2, // Accepted
            type: Number(data.type),
            discount: discountAmount.value,
            payment_method: data.payment_method,
            deliveryman_id:
                data.type === '1' && data.deliveryman_id
                    ? Number(data.deliveryman_id)
                    : null,
            description: data.description || null,
            order_items: cart.value.map((line) => ({
                menu_item_id: line.menu_item_id,
                size_id: line.size_id,
                quantity: line.quantity,
                price: Number(line.unit_price.toFixed(2)),
                toppings: line.toppings,
            })),
        }))
        .post('/orders', {
            preserveScroll: true,
            onSuccess: (visit) => {
                resetAfterOrder();
                const orderId = (
                    visit.props as {
                        flash?: { print_order_id?: number | string | null };
                    }
                ).flash?.print_order_id;
                if (orderId != null && orderId !== '') {
                    if (printTab && !printTab.closed) {
                        printTab.location.href = `/orders/${orderId}/print`;
                        return;
                    }
                    openPrintTab(orderId);
                    return;
                }
                printTab?.close();
            },
            onError: () => printTab?.close(),
        });
}

defineOptions(resourcePageBreadcrumbs('Orders', '/orders', 'create'));
</script>

<template>
    <Head :title="t('Create order')" />

    <MisPage class="!gap-0">
        <div
            class="-mt-1 grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] xl:grid-cols-[minmax(0,1fr)_24rem]"
        >
            <!-- Menu -->
            <div class="min-w-0 space-y-5">
                <div class="relative mx-auto w-full max-w-xl">
                    <Search
                        class="pointer-events-none absolute start-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="h-11 rounded-full border-border/60 bg-card ps-11 shadow-sm"
                        :placeholder="t('Search menu…')"
                    />
                </div>

                <div>
                    <div class="mb-3 flex items-end justify-between gap-3">
                        <h2 class="text-lg font-semibold tracking-tight">
                            {{ t('Menu') }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ visibleItems.length }} {{ t('items') }}
                        </p>
                    </div>

                    <div
                        class="-mx-1 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        <div class="flex w-max gap-2 px-1">
                            <button
                                v-for="chip in categoryChips"
                                :key="String(chip.id)"
                                type="button"
                                :title="chip.title"
                                :class="
                                    cn(
                                        'inline-flex shrink-0 items-center gap-2 rounded-full py-1.5 pe-3 ps-1.5 text-sm font-medium whitespace-nowrap transition',
                                        activeMenuId === chip.id
                                            ? chip.id === 'deals'
                                                ? 'bg-[var(--brand-accent)] text-[#111318] shadow-sm'
                                                : 'bg-foreground text-background shadow-sm'
                                            : 'bg-muted/80 text-muted-foreground hover:bg-muted hover:text-foreground',
                                    )
                                "
                                @click="activeMenuId = chip.id"
                            >
                                <img
                                    :src="chip.image"
                                    :alt="chip.title"
                                    class="size-7 shrink-0 rounded-full object-cover ring-2 ring-background/80"
                                />
                                <span class="max-w-[9rem] truncate">{{
                                    chip.title
                                }}</span>
                                <span
                                    :class="
                                        cn(
                                            'rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                                            activeMenuId === chip.id
                                                ? chip.id === 'deals'
                                                    ? 'bg-black/10'
                                                    : 'bg-background/20'
                                                : 'bg-background/70 text-muted-foreground',
                                        )
                                    "
                                >
                                    {{ chip.count }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!visibleItems.length"
                    class="rounded-3xl border border-dashed px-4 py-16 text-center text-sm text-muted-foreground"
                >
                    {{ t('No items found') }}
                </div>

                <div
                    v-else
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                >
                    <button
                        v-for="item in visibleItems"
                        :key="item.id"
                        type="button"
                        class="relative flex flex-col items-center rounded-3xl bg-card p-4 pt-5 text-start shadow-[0_8px_30px_-18px_rgba(15,23,42,0.35)] ring-1 ring-border/50 transition hover:ring-foreground/15 active:scale-[0.99]"
                        @click="addItem(item)"
                    >
                        <img
                            :src="itemImage(item)"
                            :alt="item.title"
                            class="size-[5.5rem] rounded-full object-cover shadow-md sm:size-24"
                        />
                        <p
                            class="mt-3 line-clamp-2 text-center text-sm font-semibold leading-snug"
                        >
                            {{ item.title }}
                        </p>
                        <p
                            class="mt-0.5 text-center text-[11px] text-muted-foreground"
                        >
                            {{ item.menu_title }}
                        </p>
                        <p
                            class="mt-2 text-sm font-semibold tabular-nums text-foreground/90"
                        >
                            {{ itemPriceLabel(item) }}
                        </p>
                        <span
                            class="pointer-events-none absolute end-3 bottom-3 flex size-8 items-center justify-center rounded-full bg-foreground text-background shadow-md"
                            aria-hidden="true"
                        >
                            <Plus class="size-4" />
                        </span>
                    </button>
                </div>
            </div>

            <!-- Cart -->
            <aside
                class="flex h-fit flex-col overflow-hidden rounded-[1.75rem] bg-card shadow-[0_12px_40px_-24px_rgba(15,23,42,0.45)] ring-1 ring-border/50 lg:sticky lg:top-3"
            >
                <div
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-border/40 px-5 py-3.5"
                >
                    <div
                        class="relative flex size-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[var(--brand-accent)] to-[color-mix(in_srgb,var(--brand-accent)_70%,#111318)] shadow-[0_6px_14px_-6px_rgba(15,23,42,0.45)]"
                    >
                        <ShoppingCart
                            class="size-[1.15rem] text-[#111318]"
                            :stroke-width="2.25"
                        />
                        <span
                            class="absolute -end-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-[#111318] px-1.5 text-[11px] font-bold leading-none tabular-nums text-white shadow-md ring-2 ring-card"
                        >
                            {{ cartCount }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div
                            class="me-1 grid grid-cols-2 gap-0.5 rounded-full bg-muted/80 p-0.5"
                        >
                            <button
                                type="button"
                                :class="
                                    cn(
                                        'rounded-full px-2.5 py-1 text-[10px] font-semibold transition',
                                        form.type === '2'
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground',
                                    )
                                "
                                @click="form.type = '2'"
                            >
                                {{ t('Collection') }}
                            </button>
                            <button
                                type="button"
                                :class="
                                    cn(
                                        'rounded-full px-2.5 py-1 text-[10px] font-semibold transition',
                                        form.type === '1'
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground',
                                    )
                                "
                                @click="form.type = '1'"
                            >
                                {{ t('Delivery') }}
                            </button>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 text-muted-foreground transition hover:bg-muted hover:text-destructive disabled:opacity-40"
                            :title="t('Clear')"
                            :disabled="!cart.length"
                            @click="clearCart"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <div
                    class="max-h-[15rem] space-y-2.5 overflow-y-auto overscroll-contain px-5 py-1 sm:max-h-[17rem]"
                >
                    <p
                        v-if="!cart.length"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ t('No items yet') }}
                    </p>

                    <div
                        v-for="line in cart"
                        :key="line.key"
                        class="flex items-start justify-between gap-3 border-b border-border/40 pb-2.5 last:border-0"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">
                                <span class="tabular-nums text-muted-foreground"
                                    >{{ line.quantity }}x</span
                                >
                                {{ line.title }}
                            </p>
                            <p
                                v-if="line.size_label || line.extras_label"
                                class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                            >
                                <span v-if="line.size_label">{{
                                    line.size_label
                                }}</span>
                                <span
                                    v-if="line.size_label && line.extras_label"
                                >
                                    ·
                                </span>
                                <span v-if="line.extras_label">{{
                                    line.extras_label
                                }}</span>
                            </p>
                            <div class="mt-2 inline-flex items-center gap-1">
                                <button
                                    type="button"
                                    class="flex size-6 items-center justify-center rounded-full bg-muted"
                                    @click.stop.prevent="
                                        setQty(line.key, line.quantity - 1)
                                    "
                                >
                                    <Minus class="size-3" />
                                </button>
                                <span
                                    class="min-w-5 text-center text-xs font-medium tabular-nums"
                                    >{{ line.quantity }}</span
                                >
                                <button
                                    type="button"
                                    class="flex size-6 items-center justify-center rounded-full bg-muted"
                                    @click.stop.prevent="
                                        setQty(line.key, line.quantity + 1)
                                    "
                                >
                                    <Plus class="size-3" />
                                </button>
                            </div>
                        </div>
                        <div class="shrink-0 text-end">
                            <p class="text-sm font-semibold tabular-nums">
                                €{{
                                    (line.unit_price * line.quantity).toFixed(2)
                                }}
                            </p>
                            <button
                                type="button"
                                class="mt-1 text-[11px] text-muted-foreground hover:text-destructive"
                                @click="removeLine(line.key)"
                            >
                                {{ t('Remove') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="shrink-0 space-y-2.5 border-t border-border/40 bg-card px-5 pt-2.5 pb-2 max-lg:pb-20"
                >
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Discount') }}
                            </p>
                            <p
                                v-if="discountPercent > 0"
                                class="text-[11px] tabular-nums text-muted-foreground"
                            >
                                −€{{ discountAmount.toFixed(2) }}
                            </p>
                        </div>
                        <div class="grid grid-cols-5 gap-1 rounded-xl bg-muted/70 p-1">
                            <button
                                v-for="pct in discountOptions"
                                :key="pct"
                                type="button"
                                :class="
                                    cn(
                                        'rounded-lg py-1.5 text-xs font-semibold tabular-nums transition',
                                        discountPercent === pct
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )
                                "
                                @click="discountPercent = pct"
                            >
                                {{ pct }}%
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-sm">
                        <div
                            class="flex items-center justify-between text-muted-foreground"
                        >
                            <span>{{ t('Subtotal') }}</span>
                            <span class="tabular-nums"
                                >€{{ cartSubtotal.toFixed(2) }}</span
                            >
                        </div>
                        <div
                            v-if="serviceFee > 0"
                            class="flex items-center justify-between text-muted-foreground"
                        >
                            <span>{{ t('Service fee') }}</span>
                            <span class="tabular-nums"
                                >€{{ serviceFee.toFixed(2) }}</span
                            >
                        </div>
                        <div
                            v-if="Number(form.type) === 1"
                            class="flex items-center justify-between text-muted-foreground"
                        >
                            <span>{{ t('Delivery') }}</span>
                            <span
                                v-if="deliveryIsFree"
                                class="text-xs font-semibold text-emerald-600"
                            >
                                {{ t('Free') }}
                            </span>
                            <span v-else class="tabular-nums"
                                >€{{ deliveryFee.toFixed(2) }}</span
                            >
                        </div>
                        <div
                            v-if="discountAmount > 0"
                            class="flex items-center justify-between text-muted-foreground"
                        >
                            <span
                                >{{ t('Discount') }} ({{
                                    discountPercent
                                }}%)</span
                            >
                            <span class="tabular-nums"
                                >−€{{ discountAmount.toFixed(2) }}</span
                            >
                        </div>
                        <div
                            v-if="storeDiscount > 0"
                            class="flex items-center justify-between text-muted-foreground"
                        >
                            <span>{{ t('Store discount') }}</span>
                            <span class="tabular-nums"
                                >−€{{ storeDiscount.toFixed(2) }}</span
                            >
                        </div>
                        <p
                            v-if="belowMinOrder"
                            class="text-[11px] text-amber-600"
                        >
                            {{ t('Minimum order') }}: €{{
                                minOrder.toFixed(2)
                            }}
                        </p>
                        <div
                            class="flex items-center justify-between border-t border-border/50 pt-2"
                        >
                            <span class="font-semibold">{{ t('Total') }}</span>
                            <span class="text-xl font-bold tabular-nums">
                                €{{ cartTotal.toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-1 rounded-xl bg-muted/70 p-1">
                        <button
                            type="button"
                            :class="
                                cn(
                                    'inline-flex items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition',
                                    form.payment_method === 'cash'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )
                            "
                            @click="form.payment_method = 'cash'"
                        >
                            <Banknote class="size-3.5" />
                            {{ t('Cash') }}
                        </button>
                        <button
                            type="button"
                            :class="
                                cn(
                                    'inline-flex items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition',
                                    form.payment_method === 'card'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )
                            "
                            @click="form.payment_method = 'card'"
                        >
                            <CreditCard class="size-3.5" />
                            {{ t('Card') }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="text-[11px] font-medium text-muted-foreground hover:text-foreground"
                        @click.stop="showCustomer = !showCustomer"
                    >
                        {{
                            showCustomer
                                ? t('Hide customer')
                                : t('Show customer')
                        }}
                    </button>

                    <div v-show="showCustomer" class="grid gap-2">
                        <Input
                            v-model="form.customer_name"
                            class="h-9 rounded-xl text-sm"
                            :placeholder="t('Name')"
                        />
                        <Input
                            v-model="form.phone_number"
                            type="tel"
                            class="h-9 rounded-xl text-sm"
                            :placeholder="t('Phone')"
                        />
                        <Input
                            v-if="form.type === '1'"
                            v-model="form.address"
                            class="h-9 rounded-xl text-sm"
                            :placeholder="t('Address')"
                        />
                    </div>

                    <Input
                        v-if="form.type === '1' && !showCustomer"
                        v-model="form.address"
                        class="h-9 rounded-xl text-sm"
                        :placeholder="t('Address')"
                    />

                    <p
                        v-if="form.errors.order_items"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.order_items }}
                    </p>
                </div>

                <div
                    class="sticky bottom-0 z-10 shrink-0 border-t border-border/50 bg-card/95 px-4 py-3 backdrop-blur-md"
                >
                    <Button
                        type="button"
                        class="h-12 w-full rounded-2xl text-base font-semibold shadow-lg"
                        :disabled="
                            !cart.length || form.processing || belowMinOrder
                        "
                        @click="submit"
                    >
                        {{
                            form.processing
                                ? t('Saving…')
                                : `${t('Place order')} · €${cartTotal.toFixed(2)}`
                        }}
                    </Button>
                </div>
            </aside>
        </div>

        <!-- Mobile / fallback: always visible bottom-right place order -->
        <div
            class="fixed end-4 bottom-4 z-40 w-[min(22rem,calc(100vw-2rem))] lg:hidden"
        >
            <Button
                type="button"
                class="h-12 w-full rounded-2xl text-base font-semibold shadow-xl"
                :disabled="!cart.length || form.processing || belowMinOrder"
                @click="submit"
            >
                {{
                    form.processing
                        ? t('Saving…')
                        : `${t('Place order')} · €${cartTotal.toFixed(2)}`
                }}
            </Button>
        </div>

        <!-- Configure modal -->
        <div
            v-if="configuring"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 backdrop-blur-[2px] sm:items-center sm:p-4"
            @click.self="closeConfig"
        >
            <div
                class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-card shadow-2xl sm:rounded-3xl"
            >
                <div class="relative border-b border-border/50 px-4 pt-4 pb-3">
                    <div class="flex items-start gap-3">
                        <img
                            :src="itemImage(configuring)"
                            :alt="configuring.title"
                            class="size-16 shrink-0 rounded-2xl object-cover shadow-md ring-1 ring-border/40"
                        />
                        <div class="min-w-0 flex-1 pe-8">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold leading-tight">
                                    {{ configuring.title }}
                                </h3>
                                <span
                                    v-if="configuring.is_deal"
                                    class="rounded-full bg-[var(--brand-accent)] px-2 py-0.5 text-[10px] font-bold text-[#111318] uppercase"
                                >
                                    {{ t('Deal') }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('Customize your order') }}
                            </p>
                            <p class="mt-1 text-base font-semibold tabular-nums">
                                €{{ configUnitPrice.toFixed(2) }}
                                <span
                                    v-if="configExtras > 0"
                                    class="ms-1 text-xs font-normal text-muted-foreground"
                                >
                                    (+€{{ configExtras.toFixed(2) }}
                                    {{ t('extras') }})
                                </span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="absolute end-3 top-3 rounded-full bg-muted p-2 text-muted-foreground hover:text-foreground"
                            @click="closeConfig"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="space-y-5 overflow-y-auto px-4 py-4">
                    <div v-if="configuring.sizes.length" class="space-y-2">
                        <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {{ t('Size') }}
                        </p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <button
                                v-for="size in configuring.sizes"
                                :key="size.id"
                                type="button"
                                :class="
                                    cn(
                                        'rounded-2xl border px-3 py-3 text-start transition',
                                        selectedSizeId === size.id
                                            ? 'border-foreground bg-foreground text-background'
                                            : 'border-border/70 hover:bg-muted/50',
                                    )
                                "
                                @click="selectedSizeId = size.id"
                            >
                                <p class="text-sm font-semibold">
                                    {{ size.size }}
                                </p>
                                <p
                                    :class="
                                        cn(
                                            'mt-0.5 text-xs tabular-nums',
                                            selectedSizeId === size.id
                                                ? 'text-background/70'
                                                : 'text-muted-foreground',
                                        )
                                    "
                                >
                                    €{{ Number(size.price).toFixed(2) }}
                                </p>
                            </button>
                        </div>
                    </div>

                    <div
                        v-for="topping in configuring.toppings"
                        :key="topping.id"
                        class="space-y-2.5"
                    >
                        <div class="flex items-end justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ topping.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="!topping.is_optional">
                                        {{ t('Pick') }}
                                        {{ topping.required || 1 }}
                                    </span>
                                    <span v-else>{{ t('Optional') }}</span>
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold tabular-nums text-muted-foreground"
                            >
                                {{ toppingSelectedCount(topping.id)
                                }}{{
                                    !topping.is_optional
                                        ? `/${topping.required || 1}`
                                        : ''
                                }}
                            </span>
                        </div>

                        <div class="grid gap-2">
                            <button
                                v-for="attachment in topping.attachments"
                                :key="attachment.id"
                                type="button"
                                :class="
                                    cn(
                                        'flex items-center gap-3 rounded-2xl border px-3 py-2.5 text-start transition',
                                        Number(
                                            selectedToppings[topping.id]?.[
                                                attachment.id
                                            ] ?? 0,
                                        ) > 0
                                            ? 'border-[var(--brand-accent)] bg-[var(--brand-accent)]/15'
                                            : 'border-border/60 hover:bg-muted/40',
                                    )
                                "
                                @click="
                                    toggleTopping(topping.id, attachment.id)
                                "
                            >
                                <span
                                    :class="
                                        cn(
                                            'flex size-5 shrink-0 items-center justify-center rounded-full border',
                                            Number(
                                                selectedToppings[topping.id]?.[
                                                    attachment.id
                                                ] ?? 0,
                                            ) > 0
                                                ? 'border-foreground bg-foreground text-background'
                                                : 'border-border',
                                        )
                                    "
                                >
                                    <Check
                                        v-if="
                                            Number(
                                                selectedToppings[topping.id]?.[
                                                    attachment.id
                                                ] ?? 0,
                                            ) > 0
                                        "
                                        class="size-3"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium"
                                    >
                                        {{ attachment.name }}
                                    </span>
                                    <span
                                        class="text-xs tabular-nums text-muted-foreground"
                                    >
                                        {{
                                            Number(attachment.price) > 0
                                                ? `+€${Number(attachment.price).toFixed(2)}`
                                                : t('Included')
                                        }}
                                    </span>
                                </span>
                                <div
                                    v-if="
                                        Number(
                                            selectedToppings[topping.id]?.[
                                                attachment.id
                                            ] ?? 0,
                                        ) > 0 && topping.is_optional
                                    "
                                    class="inline-flex items-center rounded-lg border bg-background"
                                    @click.stop
                                >
                                    <button
                                        type="button"
                                        class="p-1.5"
                                        @click="
                                            bumpTopping(
                                                topping.id,
                                                attachment.id,
                                                -1,
                                            )
                                        "
                                    >
                                        <Minus class="size-3.5" />
                                    </button>
                                    <span
                                        class="min-w-6 text-center text-sm font-medium tabular-nums"
                                    >
                                        {{
                                            selectedToppings[topping.id]?.[
                                                attachment.id
                                            ] ?? 0
                                        }}
                                    </span>
                                    <button
                                        type="button"
                                        class="p-1.5"
                                        @click="
                                            bumpTopping(
                                                topping.id,
                                                attachment.id,
                                                1,
                                            )
                                        "
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>
                            </button>
                        </div>
                    </div>

                    <p v-if="configError" class="text-sm text-destructive">
                        {{ configError }}
                    </p>
                </div>

                <div
                    class="border-t border-border/50 bg-card p-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
                >
                    <Button
                        type="button"
                        class="h-12 w-full gap-2 rounded-2xl text-base font-semibold"
                        @click="confirmConfig"
                    >
                        <Plus class="size-4" />
                        {{ t('Add to cart') }}
                        <span class="opacity-80"
                            >· €{{ configUnitPrice.toFixed(2) }}</span
                        >
                    </Button>
                </div>
            </div>
        </div>
    </MisPage>
</template>
