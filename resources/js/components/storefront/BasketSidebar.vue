<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bike, Minus, Plus, ShoppingBag, ShoppingCart, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    calculateDiscount,
    calculateFees,
    getActiveDiscounts,
    getAvailableDiscounts,
    getFees,
    type DiscountData,
    type FeesData,
} from '@/lib/storefront/api';

interface SelectedOption {
    groupTitle: string;
    items: { name: string; qty: number; price: number; id: number }[];
}

interface BasketItem {
    id: number;
    name: string;
    price: string;
    quantity: number;
    totalPrice: number;
    image?: string | null;
    selectedOptions?: SelectedOption[];
    selectedSize?: { size?: string; name?: string; price?: string | number };
}

const props = defineProps<{
    items: BasketItem[];
    updateQuantity: (index: number, newQty: number) => void;
    removeItem: (index: number) => void;
    itemClick?: (productId: number) => void;
}>();

const deliveryMode = ref<'delivery' | 'collection'>('delivery');
const feesData = ref<FeesData | null>(null);
const discountsData = ref<DiscountData[]>([]);
const discountAmount = ref(0);
const appliedDiscount = ref<DiscountData | null>(null);
const availableDiscounts = ref<
    { discount: DiscountData; meetsRequirement: boolean; amountNeeded: number }[]
>([]);

const itemCount = computed(() => props.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0));
const subtotal = computed(() =>
    props.items.reduce((sum, item) => sum + (Number(item.totalPrice) || 0), 0),
);

const fees = computed(() =>
    calculateFees(feesData.value, subtotal.value, deliveryMode.value, discountAmount.value),
);
const deliveryFee = computed(() => fees.value.deliveryFee);
const serviceFee = computed(() => fees.value.serviceFee);
const total = computed(() => fees.value.total);

const freeDeliveryLimit = computed(() =>
    feesData.value ? parseFloat(feesData.value.free_delivery_limit) : 12.0,
);
const qualifiesForFreeDelivery = computed(() => subtotal.value >= freeDeliveryLimit.value);
const amountNeeded = computed(() => Math.max(0, freeDeliveryLimit.value - subtotal.value));

const minOrderValue = computed(() =>
    feesData.value?.min_order ? parseFloat(String(feesData.value.min_order)) : 10.0,
);
const meetsMinimumOrder = computed(
    () => deliveryMode.value === 'collection' || subtotal.value >= minOrderValue.value,
);
const minOrderAmountNeeded = computed(() =>
    deliveryMode.value === 'delivery' ? Math.max(0, minOrderValue.value - subtotal.value) : 0,
);

const unmetDiscounts = computed(() => availableDiscounts.value.filter((ad) => !ad.meetsRequirement));

function syncDeliveryMode() {
    const saved = localStorage.getItem('deliveryType');
    if (saved === 'collection' || saved === 'delivery') {
        deliveryMode.value = saved;
    }
}

function setDeliveryMode(mode: 'delivery' | 'collection') {
    localStorage.setItem('deliveryType', mode);
    deliveryMode.value = mode;
    window.dispatchEvent(new Event('deliveryModeChanged'));
}

function itemDisplayPrice(item: BasketItem): string {
    if ((!item.price || item.price === 'null' || (item.price as unknown) === null) && item.selectedSize) {
        const sizePrice =
            typeof item.selectedSize.price === 'number'
                ? item.selectedSize.price
                : parseFloat(String(item.selectedSize.price || 0));
        return `€${(sizePrice * item.quantity).toFixed(2)}`;
    }
    return `€${Number(item.totalPrice).toFixed(2)}`;
}

function handleCheckout() {
    if (deliveryMode.value === 'delivery' && subtotal.value < minOrderValue.value) {
        alert(
            `Minimum order for delivery is €${minOrderValue.value.toFixed(2)}. Add €${minOrderAmountNeeded.value.toFixed(2)} more to checkout.`,
        );
        return;
    }
    router.visit('/checkout');
}

const onDeliveryModeChanged = () => syncDeliveryMode();
const onStorage = (e: StorageEvent) => {
    if (e.key === 'deliveryType') syncDeliveryMode();
};

onMounted(async () => {
    try {
        const [fees, discounts] = await Promise.all([getFees(), getActiveDiscounts()]);
        feesData.value = fees;
        discountsData.value = discounts;
    } catch {
        // keep defaults
    }

    syncDeliveryMode();
    window.addEventListener('deliveryModeChanged', onDeliveryModeChanged);
    window.addEventListener('storage', onStorage);
});

onUnmounted(() => {
    window.removeEventListener('deliveryModeChanged', onDeliveryModeChanged);
    window.removeEventListener('storage', onStorage);
});

watch([subtotal, discountsData], () => {
    if (discountsData.value.length > 0 && subtotal.value > 0) {
        const dayOfWeek = new Date().toLocaleDateString('en-US', { weekday: 'long' });
        const discountResult = calculateDiscount(discountsData.value, subtotal.value, dayOfWeek);
        discountAmount.value = discountResult.discountAmount;
        appliedDiscount.value = discountResult.discount;
        availableDiscounts.value = getAvailableDiscounts(discountsData.value, subtotal.value, dayOfWeek);
    } else {
        discountAmount.value = 0;
        appliedDiscount.value = null;
        availableDiscounts.value = [];
    }
});
</script>

<template>
    <aside class="flex h-full w-full flex-col bg-card border-l border-border">
        <div class="shrink-0 px-4 pt-4 pb-3 space-y-3 border-b border-border">
            <div class="flex items-baseline justify-between gap-2">
                <h2 class="text-lg font-bold text-foreground">Your basket</h2>
                <span v-if="itemCount" class="text-xs text-muted-foreground tabular-nums">
                    {{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-muted">
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-lg py-2 text-sm font-semibold transition"
                    :class="
                        deliveryMode === 'delivery'
                            ? 'bg-orange-500 text-white shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setDeliveryMode('delivery')"
                >
                    <Bike class="size-4" />
                    Delivery
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-lg py-2 text-sm font-semibold transition"
                    :class="
                        deliveryMode === 'collection'
                            ? 'bg-orange-500 text-white shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setDeliveryMode('collection')"
                >
                    <ShoppingBag class="size-4" />
                    Collection
                </button>
            </div>

            <p
                v-if="deliveryMode === 'delivery'"
                class="text-xs leading-relaxed"
                :class="qualifiesForFreeDelivery ? 'text-green-600 dark:text-green-400 font-medium' : 'text-muted-foreground'"
            >
                <template v-if="qualifiesForFreeDelivery">Free delivery unlocked</template>
                <template v-else>
                    Free delivery from €{{ freeDeliveryLimit.toFixed(2) }} —
                    <span class="text-orange-500 font-semibold">€{{ amountNeeded.toFixed(2) }} to go</span>
                </template>
            </p>
        </div>

        <div v-if="items.length === 0" class="flex flex-1 flex-col items-center justify-center gap-2 px-6 text-center">
            <div class="mb-1 flex size-14 items-center justify-center rounded-2xl bg-muted">
                <ShoppingCart class="size-7 text-muted-foreground" :stroke-width="1.5" />
            </div>
            <p class="font-semibold text-foreground">Basket is empty</p>
            <p class="text-sm text-muted-foreground">Tap a dish to add it here</p>
        </div>

        <template v-else>
            <div class="min-h-0 flex-1 overflow-y-auto px-3 py-3">
                <ul class="space-y-2">
                    <li
                        v-for="(item, index) in items"
                        :key="`${item.id}-${index}`"
                        class="rounded-xl border border-border bg-background px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="w-full text-left text-sm font-semibold leading-snug text-foreground line-clamp-2 hover:text-orange-500"
                            @click="item.id && itemClick?.(item.id)"
                        >
                            {{ item.name }}
                            <span v-if="item.selectedSize" class="font-normal text-muted-foreground">
                                ({{ item.selectedSize.size || item.selectedSize.name }})
                            </span>
                        </button>

                        <p
                            v-if="item.selectedOptions?.length"
                            class="mt-0.5 text-[11px] text-muted-foreground line-clamp-2"
                        >
                            <template v-for="(group, gIdx) in item.selectedOptions" :key="gIdx">
                                <span v-for="(opt, oIdx) in group.items" :key="`${gIdx}-${oIdx}`">
                                    {{ oIdx || gIdx ? ', ' : '' }}{{ opt.qty > 1 ? `${opt.qty}× ` : ''
                                    }}{{ opt.name }}
                                </span>
                            </template>
                        </p>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="text-sm font-bold tabular-nums text-orange-500">{{
                                itemDisplayPrice(item)
                            }}</span>

                            <div class="inline-flex items-center rounded-full border border-border bg-muted/60">
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center text-muted-foreground hover:text-foreground"
                                    :aria-label="item.quantity <= 1 ? 'Remove item' : 'Decrease quantity'"
                                    @click="
                                        item.quantity <= 1
                                            ? removeItem(index)
                                            : updateQuantity(index, item.quantity - 1)
                                    "
                                >
                                    <Trash2 v-if="item.quantity <= 1" class="size-3.5" />
                                    <Minus v-else class="size-3.5" />
                                </button>
                                <span class="w-5 text-center text-sm font-bold tabular-nums text-foreground">{{
                                    item.quantity
                                }}</span>
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center text-muted-foreground hover:text-foreground"
                                    aria-label="Increase quantity"
                                    @click="updateQuantity(index, item.quantity + 1)"
                                >
                                    <Plus class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="shrink-0 space-y-2 border-t border-border bg-card px-4 py-3">
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Subtotal</span>
                    <span class="font-medium tabular-nums text-foreground">€{{ subtotal.toFixed(2) }}</span>
                </div>

                <div
                    v-if="discountAmount > 0 && appliedDiscount"
                    class="flex justify-between gap-2 text-sm text-green-600 dark:text-green-400"
                >
                    <span class="truncate">Discount{{ appliedDiscount.name ? ` · ${appliedDiscount.name}` : '' }}</span>
                    <span class="shrink-0 font-semibold tabular-nums">-€{{ discountAmount.toFixed(2) }}</span>
                </div>

                <div
                    v-if="unmetDiscounts.length"
                    class="rounded-lg bg-blue-50 px-2.5 py-2 text-[11px] text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                >
                    <p v-for="(d, idx) in unmetDiscounts" :key="idx">
                        <span class="font-semibold">{{ d.discount.name }}</span>
                        · €{{ d.amountNeeded.toFixed(2) }} more
                    </p>
                </div>

                <div v-if="deliveryMode === 'delivery'" class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Delivery</span>
                    <span
                        class="font-semibold tabular-nums"
                        :class="deliveryFee === 0 ? 'text-green-600 dark:text-green-400' : 'text-foreground'"
                    >
                        {{ deliveryFee === 0 ? 'Free' : `€${deliveryFee.toFixed(2)}` }}
                    </span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Service fee</span>
                    <span class="font-medium tabular-nums text-foreground">€{{ serviceFee.toFixed(2) }}</span>
                </div>

                <div class="flex items-center justify-between border-t border-border pt-2">
                    <span class="font-bold text-foreground">Total</span>
                    <span class="text-lg font-bold tabular-nums text-orange-500">€{{ total.toFixed(2) }}</span>
                </div>

                <p
                    v-if="deliveryMode === 'delivery' && !meetsMinimumOrder"
                    class="rounded-lg bg-orange-500/10 px-2.5 py-2 text-xs font-medium text-orange-600 dark:text-orange-400"
                >
                    Minimum €{{ minOrderValue.toFixed(2) }} for delivery — add €{{ minOrderAmountNeeded.toFixed(2) }}
                </p>

                <button
                    type="button"
                    :disabled="deliveryMode === 'delivery' && !meetsMinimumOrder"
                    class="w-full rounded-xl py-3 text-sm font-bold transition disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground disabled:shadow-none bg-orange-500 text-white hover:bg-orange-600 shadow-md shadow-orange-500/20"
                    @click="handleCheckout"
                >
                    Go to checkout · €{{ total.toFixed(2) }}
                </button>
            </div>
        </template>
    </aside>
</template>
