<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bike, Info, Minus, Plus, ShoppingBag, ShoppingCart, Trash2, X } from '@lucide/vue';
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

interface SelectedAddOn {
    id: number;
    name: string;
    price: number;
    quantity?: number;
}

interface BasketItem {
    id: number;
    name: string;
    price: string;
    quantity: number;
    totalPrice: number;
    image?: string | null;
    selectedOptions?: SelectedOption[];
    selectedAddOns?: SelectedAddOn[];
    selectedSize?: { size?: string; name?: string; price?: string | number };
}

const props = defineProps<{
    items: BasketItem[];
    updateQuantity: (index: number, newQty: number) => void;
    removeItem: (index: number) => void;
    itemClick?: (productId: number) => void;
}>();

const isOpen = ref(false);
const deliveryMode = ref<'delivery' | 'collection'>('delivery');
const feesData = ref<FeesData | null>(null);
const discountsData = ref<DiscountData[]>([]);
const discountAmount = ref(0);
const appliedDiscount = ref<DiscountData | null>(null);
const availableDiscounts = ref<
    { discount: DiscountData; meetsRequirement: boolean; amountNeeded: number }[]
>([]);

const subtotal = computed(() => props.items.reduce((sum, item) => sum + item.totalPrice, 0));
const itemCount = computed(() => props.items.reduce((sum, item) => sum + item.quantity, 0));

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

function discountValueNum(d: DiscountData): number {
    return typeof d.discount_value === 'number'
        ? d.discount_value
        : parseFloat(String(d.discount_value)) || 0;
}

function minPurchaseNum(d: DiscountData): number {
    if (!d.minimum_purchase_amount || d.minimum_purchase_amount <= 0) return 0;
    return typeof d.minimum_purchase_amount === 'number'
        ? d.minimum_purchase_amount
        : parseFloat(String(d.minimum_purchase_amount)) || 0;
}

function handleCheckout() {
    if (deliveryMode.value === 'delivery' && subtotal.value < minOrderValue.value) {
        alert(
            `Minimum order value for delivery is €${minOrderValue.value.toFixed(2)}. Your order total is €${subtotal.value.toFixed(2)}. Please add €${minOrderAmountNeeded.value.toFixed(2)} more to proceed.`,
        );
        return;
    }
    isOpen.value = false;
    router.visit('/checkout');
}

function handleItemClick(item: BasketItem) {
    if (props.itemClick && item.id) {
        props.itemClick(item.id);
        isOpen.value = false;
    }
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
    <template v-if="items.length > 0">
        <button
            type="button"
            class="fixed bottom-4 left-4 right-4 lg:hidden z-40 flex items-center justify-between rounded-2xl bg-orange-500 px-5 py-3.5 text-white font-bold shadow-lg shadow-orange-500/30 hover:bg-orange-600 transition"
            @click="isOpen = true"
        >
            <span class="inline-flex items-center gap-2.5">
                <span class="relative inline-flex">
                    <ShoppingCart class="size-5" />
                    <span
                        class="absolute -right-2 -top-2 flex size-5 items-center justify-center rounded-full bg-white text-[11px] font-bold text-orange-500"
                    >
                        {{ itemCount }}
                    </span>
                </span>
                View basket
            </span>
            <span class="tabular-nums">€{{ total.toFixed(2) }}</span>
        </button>

        <div
            v-if="isOpen"
            class="fixed inset-0 bg-black/70 dark:bg-black/80 backdrop-blur-sm z-50 lg:hidden"
            @click="isOpen = false"
        >
            <div
                class="fixed bottom-0 left-0 right-0 bg-background rounded-t-3xl h-[90vh] flex flex-col overflow-hidden border-t border-border shadow-2xl transition-colors duration-300"
                @click.stop
            >
                <div class="flex justify-between items-center p-5 border-b border-border flex-shrink-0">
                    <h2 class="text-xl font-bold text-foreground">Basket</h2>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground transition p-2 hover:bg-accent rounded-full -mr-2"
                        @click="isOpen = false"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-4 border-b border-border flex-shrink-0">
                    <div class="flex bg-card rounded-full p-1 mb-3 border border-border">
                        <button
                            type="button"
                            class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-full text-sm transition"
                            :class="deliveryMode === 'delivery' ? 'bg-orange-500 text-white shadow-sm' : 'text-muted-foreground'"
                            @click="setDeliveryMode('delivery')"
                        >
                            <Bike class="w-4 h-4" />
                            <span class="font-medium">Delivery</span>
                        </button>
                        <button
                            type="button"
                            class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-full text-sm transition"
                            :class="deliveryMode === 'collection' ? 'bg-orange-500 text-white shadow-sm' : 'text-muted-foreground'"
                            @click="setDeliveryMode('collection')"
                        >
                            <ShoppingBag class="w-4 h-4" />
                            <span class="font-medium">Collection</span>
                        </button>
                    </div>

                    <div
                        v-if="deliveryMode === 'delivery'"
                        class="px-3 py-2 rounded-xl text-xs border border-border bg-card"
                    >
                        <p v-if="qualifiesForFreeDelivery" class="text-green-500 font-medium">✓ Free delivery unlocked</p>
                        <p v-else class="text-muted-foreground">
                            Free delivery over €{{ freeDeliveryLimit.toFixed(2) }} ·
                            <span class="text-orange-500 font-medium">€{{ amountNeeded.toFixed(2) }} more</span>
                        </p>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 min-h-0">
                    <div
                        v-for="(item, index) in items"
                        :key="`${item.id}-${index}`"
                        class="rounded-xl border border-border bg-card px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="text-left text-[13px] font-semibold text-foreground hover:text-orange-500 transition line-clamp-2 w-full"
                            @click="handleItemClick(item)"
                        >
                            {{ item.name
                            }}{{
                                item.selectedSize
                                    ? ` (${item.selectedSize.size || item.selectedSize.name})`
                                    : ''
                            }}
                        </button>
                        <div
                            v-if="item.selectedOptions?.length"
                            class="mt-0.5 text-[10px] text-muted-foreground line-clamp-1"
                        >
                            <template v-for="(group, gIdx) in item.selectedOptions" :key="gIdx">
                                <span v-for="(opt, oIdx) in group.items" :key="`${gIdx}-${oIdx}`">
                                    {{ oIdx || gIdx ? ', ' : '' }}{{ opt.qty > 1 ? `${opt.qty}× ` : '' }}{{ opt.name }}
                                </span>
                            </template>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <span class="text-sm font-bold text-orange-500 tabular-nums tracking-tight">{{
                                itemDisplayPrice(item)
                            }}</span>
                            <div class="flex items-center bg-muted/70 rounded-full h-7">
                                <button
                                    type="button"
                                    class="w-7 h-7 inline-flex items-center justify-center text-muted-foreground hover:text-foreground"
                                    @click="
                                        item.quantity <= 1
                                            ? removeItem(index)
                                            : updateQuantity(index, item.quantity - 1)
                                    "
                                >
                                    <Trash2 v-if="item.quantity <= 1" class="w-3 h-3" />
                                    <Minus v-else class="w-3 h-3" />
                                </button>
                                <span class="text-foreground font-bold w-4 text-center text-xs tabular-nums">{{
                                    item.quantity
                                }}</span>
                                <button
                                    type="button"
                                    class="w-7 h-7 inline-flex items-center justify-center text-muted-foreground hover:text-foreground"
                                    @click="updateQuantity(index, item.quantity + 1)"
                                >
                                    <Plus class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border bg-background flex-shrink-0">
                    <div class="px-4 pt-4 pb-2 space-y-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="text-foreground font-medium tabular-nums">€{{ subtotal.toFixed(2) }}</span>
                        </div>
                        <div
                            v-if="discountAmount > 0 && appliedDiscount"
                            class="flex justify-between items-start text-sm"
                        >
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-green-600 dark:text-green-400 font-medium flex items-center gap-1.5"
                                >
                                    Discount {{ appliedDiscount.name && `(${appliedDiscount.name})` }}
                                    <span class="text-green-600 dark:text-green-400 font-normal">
                                        {{
                                            appliedDiscount.discount_type === 'percentage'
                                                ? `(${discountValueNum(appliedDiscount)}%)`
                                                : `(€${discountValueNum(appliedDiscount).toFixed(2)})`
                                        }}
                                    </span>
                                </span>
                                <span
                                    v-if="
                                        appliedDiscount.minimum_purchase_amount &&
                                        appliedDiscount.minimum_purchase_amount > 0
                                    "
                                    class="text-xs text-green-600/80 dark:text-green-400/80"
                                >
                                    Min. purchase: €{{ minPurchaseNum(appliedDiscount).toFixed(2) }}
                                </span>
                            </div>
                            <span class="text-green-600 dark:text-green-400 font-semibold tabular-nums">
                                -€{{ discountAmount.toFixed(2) }}
                            </span>
                        </div>

                        <div
                            v-if="unmetDiscounts.length > 0"
                            class="bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg p-2 space-y-1.5"
                        >
                            <div class="text-xs font-semibold text-blue-700 dark:text-blue-400">
                                💡 Available Discounts
                            </div>
                            <div
                                v-for="(availableDiscount, idx) in unmetDiscounts"
                                :key="idx"
                                class="text-xs text-blue-600 dark:text-blue-400"
                            >
                                <span class="font-medium">{{ availableDiscount.discount.name }}</span>
                                -
                                {{
                                    availableDiscount.discount.discount_type === 'percentage'
                                        ? `${discountValueNum(availableDiscount.discount)}% off`
                                        : `€${discountValueNum(availableDiscount.discount).toFixed(2)} off`
                                }}
                                when you spend €{{ minPurchaseNum(availableDiscount.discount).toFixed(2) }}
                                <span
                                    v-if="availableDiscount.amountNeeded > 0"
                                    class="font-semibold text-blue-700 dark:text-blue-300 ml-1"
                                >
                                    (Spend €{{ availableDiscount.amountNeeded.toFixed(2) }} more)
                                </span>
                            </div>
                        </div>

                        <div v-if="deliveryMode === 'delivery'" class="flex justify-between items-center text-sm">
                            <span class="text-muted-foreground flex items-center gap-1">
                                Delivery fee <Info class="w-3 h-3" />
                                <span
                                    v-if="
                                        deliveryFee === 0 &&
                                        feesData &&
                                        subtotal >= parseFloat(feesData.free_delivery_limit)
                                    "
                                    class="text-green-500 text-xs"
                                    >(Free!)</span
                                >
                            </span>
                            <span
                                class="font-semibold tabular-nums"
                                :class="deliveryFee === 0 ? 'text-green-500' : 'text-foreground'"
                            >
                                {{ deliveryFee === 0 ? 'Free' : `€${deliveryFee.toFixed(2)}` }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-muted-foreground flex items-center gap-1">
                                Service fee <Info class="w-3 h-3" />
                            </span>
                            <span class="text-foreground font-medium tabular-nums">€{{ serviceFee.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t border-border pb-4">
                            <span class="text-foreground font-bold text-base">Total</span>
                            <span class="text-orange-500 font-bold text-lg tabular-nums tracking-tight"
                                >€{{ total.toFixed(2) }}</span
                            >
                        </div>
                    </div>
                    <div v-if="deliveryMode === 'delivery' && !meetsMinimumOrder" class="px-4 pb-3">
                        <div class="px-3 py-2 rounded-lg text-xs border border-orange-500/50 bg-orange-500/10">
                            <p class="text-orange-500 font-medium">
                                Minimum order for delivery is €{{ minOrderValue.toFixed(2) }}. Add €{{
                                    minOrderAmountNeeded.toFixed(2)
                                }}
                                more to checkout.
                            </p>
                        </div>
                    </div>
                    <div class="px-4 pb-6">
                        <button
                            type="button"
                            :disabled="!meetsMinimumOrder && deliveryMode === 'delivery'"
                            class="w-full font-bold py-4 rounded-full transition text-lg"
                            :class="
                                !meetsMinimumOrder && deliveryMode === 'delivery'
                                    ? 'bg-muted text-muted-foreground cursor-not-allowed'
                                    : 'bg-orange-500 text-white hover:bg-orange-600 active:bg-orange-700 dark:bg-orange-600'
                            "
                            @click="handleCheckout"
                        >
                            Checkout · €{{ total.toFixed(2) }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>
