<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bike,
    Clock,
    CreditCard,
    MapPin,
    Phone,
    ShoppingBag,
    User,
} from '@lucide/vue';

import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Header from '@/components/storefront/Header.vue';
import MyAccountModal from '@/components/storefront/MyAccountModal.vue';
import StorefrontSeo from '@/components/storefront/StorefrontSeo.vue';
import { useStorefrontAuth } from '@/composables/storefront/useStorefrontAuth';
import {
    useStorefrontBasket,
    type StorefrontBasketItem,
} from '@/composables/storefront/useStorefrontBasket';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import {
    calculateDiscount,
    calculateFees,
    createCheckoutSession,
    getActiveDiscounts,
    getAvailableDiscounts,
    getFees,
    getSettings,
    placeOrder,
    type DiscountData,
    type FeesData,
    type PlaceOrderData,
    type SettingsData,
} from '@/lib/storefront/api';

defineOptions({ layout: StorefrontLayout });

type CheckoutBasketItem = StorefrontBasketItem & {
    selectedSize?: { id?: number; size?: string; name?: string; price?: string | number };
    selectedOptions?: { items: { id: number; name: string; qty: number; price: number }[] }[];
    selectedAddOns?: { id: number; name: string; price: number; quantity?: number }[];
};

type DateOption = { value: string; label: string; short: string; date: Date; isToday: boolean };

const OPEN_HOUR = 15; // 15:00
const PREP_MINUTES = 45;

const auth = useStorefrontAuth();
const basket = useStorefrontBasket();
const user = computed(() => auth.user.value);
const isCheckingAuth = ref(true);
const items = computed(() => basket.itemsRef.value as CheckoutBasketItem[]);

function goBack() {
    window.history.back();
}

function setDeliveryType(type: 'delivery' | 'collection') {
    deliveryType.value = type;
    localStorage.setItem('deliveryType', type);
}

const subtotal = computed(() => basket.subtotal.value);
const deliveryFee = ref(0);
const serviceFee = ref(0.99);
const total = ref(0);
const discountAmount = ref(0);
const appliedDiscount = ref<DiscountData | null>(null);
const availableDiscounts = ref<
    { discount: DiscountData; meetsRequirement: boolean; amountNeeded: number }[]
>([]);
const feesData = ref<FeesData | null>(null);
const settingsData = ref<SettingsData | null>(null);
const discountsData = ref<DiscountData[]>([]);
const deliveryType = ref<'delivery' | 'collection'>('delivery');
const selectedDate = ref('');
const selectedTime = ref('ASAP');
const description = ref('');
const paymentMethod = ref<'cash' | 'online'>('cash');
const customerName = ref('');
const phoneNumber = ref('');
const areaCode = ref('');
const address = ref('');
const isSubmitting = ref(false);
const error = ref('');
const showItems = ref(true);
const isAccountModalOpen = ref(false);

function closingHourFor(date: Date): { hour: number; minute: number } {
    const day = date.getDay(); // 0 Sun … 6 Sat
    // Fri/Sat/Sun later close
    if (day === 5 || day === 6 || day === 0) return { hour: 1, minute: 30 };
    return { hour: 0, minute: 30 };
}

function pad(n: number) {
    return String(n).padStart(2, '0');
}

function formatSlot(d: Date) {
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const dateOptions = computed((): DateOption[] => {
    const options: DateOption[] = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    for (let i = 0; i < 2; i++) {
        const date = new Date(today);
        date.setDate(date.getDate() + i);
        const dateStr = date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit' });
        const label = i === 0 ? 'Today' : 'Tomorrow';
        options.push({
            value: `${dateStr}|${label}`,
            label,
            short: dateStr,
            date,
            isToday: i === 0,
        });
    }
    return options;
});

const selectedDateOption = computed(
    () => dateOptions.value.find((d) => d.value === selectedDate.value) ?? dateOptions.value[0],
);

function serviceWindowFor(day: Date): { start: Date; end: Date } {
    const start = new Date(day);
    start.setHours(OPEN_HOUR, 0, 0, 0);

    const close = closingHourFor(day);
    const end = new Date(day);
    if (close.hour < OPEN_HOUR) {
        end.setDate(end.getDate() + 1);
    }
    end.setHours(close.hour, close.minute, 0, 0);
    return { start, end };
}

function roundUpToSlot(d: Date, stepMinutes = 15): Date {
    const next = new Date(d);
    next.setSeconds(0, 0);
    const mins = next.getMinutes();
    const rem = mins % stepMinutes;
    if (rem !== 0) next.setMinutes(mins + (stepMinutes - rem), 0, 0);
    return next;
}

const timeSlots = computed(() => {
    const opt = selectedDateOption.value;
    if (!opt) return [];

    const slots: string[] = [];
    const now = new Date();
    const { start: openAt, end } = serviceWindowFor(opt.date);
    // Always book at least prep minutes ahead — never past / current times
    const earliest = roundUpToSlot(new Date(now.getTime() + PREP_MINUTES * 60 * 1000));
    const start = new Date(openAt);

    if (opt.isToday) {
        if (earliest > start) start.setTime(earliest.getTime());
        const kitchenOpen = now >= openAt && now <= end;
        if (kitchenOpen && earliest <= end) slots.push('ASAP');
    }

    if (start > end) return slots;

    const cursor = new Date(start);
    while (cursor <= end) {
        // Absolute datetime must be in the future
        if (cursor.getTime() > now.getTime()) {
            const label = formatSlot(cursor);
            if (!slots.includes(label)) slots.push(label);
        }
        cursor.setMinutes(cursor.getMinutes() + 15);
    }

    return slots;
});

const asapSlot = computed(() => (timeSlots.value.includes('ASAP') ? 'ASAP' : null));
const laterSlots = computed(() => timeSlots.value.filter((s) => s !== 'ASAP'));

watch(
    timeSlots,
    (slots) => {
        if (!slots.length) {
            selectedTime.value = '';
            return;
        }
        if (!slots.includes(selectedTime.value)) {
            selectedTime.value = slots[0] ?? '';
        }
    },
    { immediate: true },
);

const unmetDiscounts = computed(() => availableDiscounts.value.filter((ad) => !ad.meetsRequirement));

function formatDateTime() {
    if (!selectedDateOption.value || !selectedTime.value) return '';
    const dayLabel = selectedDateOption.value.label;
    if (selectedTime.value === 'ASAP') return `${dayLabel} · ASAP (~${PREP_MINUTES} min)`;
    return `${dayLabel} · ${selectedTime.value}`;
}

function resolveOrderDateTime(): { date: string; time: string } {
    const opt = selectedDateOption.value;
    const dateStr = opt?.short ?? new Date().toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit' });
    const dayLabel = opt?.isToday ? 'Today' : opt?.label ?? 'Today';
    const date = `${dateStr} - ${dayLabel}`;

    if (selectedTime.value === 'ASAP') {
        const { start: openAt, end } = serviceWindowFor(opt?.date ?? new Date());
        let asap = roundUpToSlot(new Date(Date.now() + PREP_MINUTES * 60 * 1000));
        if (asap < openAt) asap = new Date(openAt);
        if (asap > end) asap = new Date(end);
        return { date, time: formatSlot(asap) };
    }
    return { date, time: selectedTime.value };
}

function discountDisplayValue(d: DiscountData) {
    const val =
        typeof d.discount_value === 'number' ? d.discount_value : parseFloat(String(d.discount_value)) || 0;
    return d.discount_type === 'percentage' ? `${val}%` : `€${val.toFixed(2)}`;
}

function minPurchase(d: DiscountData) {
    if (!d.minimum_purchase_amount || d.minimum_purchase_amount <= 0) return 0;
    return typeof d.minimum_purchase_amount === 'number'
        ? d.minimum_purchase_amount
        : parseFloat(String(d.minimum_purchase_amount)) || 0;
}

function fillFromUser() {
    const u = user.value;
    if (!u) return;
    if (!customerName.value.trim()) customerName.value = String(u.name || '');
    if (!phoneNumber.value.trim()) phoneNumber.value = String(u.phone_number || '');
    if (!areaCode.value.trim()) areaCode.value = String(u.area_code || '');
    const profileAddress = String(u.address || '');
    const savedAddress = localStorage.getItem('user_address') || '';
    if (!address.value.trim()) {
        address.value = profileAddress || savedAddress;
        if (address.value.trim()) localStorage.setItem('user_address', address.value.trim());
    }
}

const checkAuth = () => {
    auth.syncFromStorage();
    if (auth.isAuthenticated.value) {
        isAccountModalOpen.value = false;
        fillFromUser();
    } else {
        isAccountModalOpen.value = true;
        error.value = 'Please log in to checkout.';
    }
    isCheckingAuth.value = false;
};

const handleAuthChange = () => checkAuth();

onMounted(async () => {
    basket.reload();
    if (items.value.length === 0) {
        router.visit('/');
        return;
    }

    const savedDeliveryType = localStorage.getItem('deliveryType');
    if (savedDeliveryType === 'collection' || savedDeliveryType === 'delivery') {
        deliveryType.value = savedDeliveryType;
    }

    if (dateOptions.value[0]) {
        selectedDate.value = dateOptions.value[0].value;
    }

    checkAuth();
    window.addEventListener('auth:login', handleAuthChange);
    window.addEventListener('auth:logout', handleAuthChange);

    try {
        const [fees, settings, discounts] = await Promise.all([
            getFees(),
            getSettings(),
            getActiveDiscounts(),
        ]);
        feesData.value = fees;
        settingsData.value = settings;
        discountsData.value = discounts;
    } catch (e) {
        console.error('Failed to fetch data:', e);
    }
});

onUnmounted(() => {
    window.removeEventListener('auth:login', handleAuthChange);
    window.removeEventListener('auth:logout', handleAuthChange);
});

watch([subtotal, selectedDate, discountsData], () => {
    if (discountsData.value.length > 0 && subtotal.value > 0) {
        const dayOfWeek =
            selectedDateOption.value?.date.toLocaleDateString('en-US', { weekday: 'long' }) ||
            new Date().toLocaleDateString('en-US', { weekday: 'long' });
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

watch([deliveryType, subtotal, feesData, discountAmount], () => {
    const calculated = calculateFees(feesData.value, subtotal.value, deliveryType.value, discountAmount.value);
    deliveryFee.value = calculated.deliveryFee;
    serviceFee.value = calculated.serviceFee;
    total.value = calculated.total;
});

watch(user, (u) => {
    if (!u) {
        const savedAddress = localStorage.getItem('user_address') || '';
        if (savedAddress && !address.value) address.value = savedAddress;
        return;
    }
    customerName.value = String(u.name || customerName.value || '');
    phoneNumber.value = String(u.phone_number || phoneNumber.value || '');
    areaCode.value = String(u.area_code || areaCode.value || '');
    const profileAddress = String(u.address || '');
    const savedAddress = localStorage.getItem('user_address') || '';
    if (profileAddress) {
        address.value = profileAddress;
        localStorage.setItem('user_address', profileAddress);
    } else if (savedAddress && !address.value) {
        address.value = savedAddress;
    }
});

async function handleSubmit() {
    error.value = '';
    auth.syncFromStorage();
    if (!user.value) {
        error.value = 'Please log in to place an order.';
        isAccountModalOpen.value = true;
        return;
    }
    if (!customerName.value.trim() || customerName.value.trim().length < 2) {
        error.value = 'Please enter your name.';
        return;
    }
    if (!phoneNumber.value.trim()) {
        error.value = 'Phone number is required.';
        return;
    }
    if (deliveryType.value === 'delivery' && !areaCode.value.trim()) {
        error.value = 'Area code is required for delivery.';
        return;
    }
    if (!selectedDate.value || !selectedTime.value || !timeSlots.value.length) {
        error.value = 'Please choose a future date and time.';
        return;
    }
    if (deliveryType.value === 'delivery') {
        const minOrderValue = feesData.value?.min_order
            ? parseFloat(String(feesData.value.min_order))
            : settingsData.value?.min_order_value || 10.0;
        if (minOrderValue > 0 && subtotal.value < minOrderValue) {
            error.value = `Minimum for delivery is €${minOrderValue.toFixed(2)}. Add €${(minOrderValue - subtotal.value).toFixed(2)} more.`;
            return;
        }
    }
    if (deliveryType.value === 'delivery' && !address.value.trim()) {
        error.value = 'Please enter your delivery address.';
        return;
    }

    isSubmitting.value = true;
    try {
        const { date, time } = resolveOrderDateTime();

        const orderItems: PlaceOrderData['order_items'] = items.value.map((item) => {
            const attachments: Array<{ id: number; quantity: number }> = [];
            item.selectedOptions?.forEach((group) => {
                group.items.forEach((opt) => {
                    if (opt.id) attachments.push({ id: Number(opt.id), quantity: Number(opt.qty) });
                });
            });
            item.selectedAddOns?.forEach((addon) => {
                attachments.push({ id: Number(addon.id), quantity: Number(addon.quantity) || 1 });
            });
            return {
                food: Number(item.id),
                quantity: Number(item.quantity),
                size: item.selectedSize?.id != null ? Number(item.selectedSize.id) : undefined,
                price: Number(item.totalPrice) / Number(item.quantity),
                attachments: attachments.length > 0 ? attachments : undefined,
            };
        });

        const orderData: PlaceOrderData = {
            total_price: total.value,
            subtotal: subtotal.value,
            customer: Number(user.value.id),
            description: description.value.trim() || undefined,
            phone_number: phoneNumber.value.trim(),
            payment_method: paymentMethod.value === 'cash' ? 'cash' : 'online',
            area_code: deliveryType.value === 'delivery' ? areaCode.value.trim() : '',
            houseadress: deliveryType.value === 'delivery' ? address.value.trim() : '',
            longitude: '0',
            latitude: '0',
            date,
            time,
            delivery_type: deliveryType.value,
            order_items: orderItems,
            discount: discountAmount.value,
        };

        const saveProfile = async () => {
            if (!user.value) return;
            try {
                await auth.updateProfile(Number(user.value.id), {
                    name: customerName.value.trim(),
                    email: String(user.value.email),
                    phone_number: phoneNumber.value.trim() || String(user.value.phone_number || ''),
                    area_code:
                        deliveryType.value === 'delivery'
                            ? areaCode.value.trim()
                            : String(user.value.area_code || ''),
                    address:
                        deliveryType.value === 'delivery' && address.value.trim()
                            ? address.value.trim()
                            : String(user.value.address || ''),
                    latitude: String(user.value.latitude || ''),
                    longitude: String(user.value.longitude || ''),
                });
            } catch {
                // non-blocking
            }
        };

        if (paymentMethod.value === 'online') {
            const sessionData = await createCheckoutSession({
                order_data: orderData,
                items: items.value,
                subtotal: subtotal.value,
                deliveryFee: deliveryFee.value,
                serviceFee: serviceFee.value,
                total: total.value,
            });
            await saveProfile();
            if (sessionData.url) {
                window.location.href = sessionData.url;
                return;
            }
            throw new Error('Failed to create payment session');
        }

        await saveProfile();
        const response = await placeOrder(orderData);
        if (response.success) {
            basket.clear();
            router.visit('/my-orders');
        } else {
            error.value = response.message || response.error || 'Failed to place order';
        }
    } catch (err: unknown) {
        const message = err instanceof Error ? err.message : 'Failed to place order. Please try again.';
        if (
            message.includes('Session expired') ||
            message.includes('Authentication required') ||
            message.includes('401')
        ) {
            error.value = 'Your session has expired. Please log in again.';
            isAccountModalOpen.value = true;
            await auth.logout();
        } else error.value = message;
    } finally {
        isSubmitting.value = false;
    }
}

function onLoginModalClose() {
    isAccountModalOpen.value = false;
    auth.syncFromStorage();
    if (!auth.isAuthenticated.value) router.visit('/');
}

function onAccountModalClose() {
    isAccountModalOpen.value = false;
    auth.syncFromStorage();
}

function onAddressInput() {
    if (address.value.trim()) localStorage.setItem('user_address', address.value.trim());
}

const fieldClass =
    'w-full h-11 px-3.5 rounded-xl border border-border bg-background text-sm text-foreground focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition';
</script>

<template>
    <StorefrontSeo title="Checkout" path="/checkout" :no-index="true" />

    <div
        v-if="isCheckingAuth"
        class="flex min-h-screen items-center justify-center bg-background"
    >
        <p class="text-sm text-muted-foreground">Loading checkout…</p>
    </div>

    <div v-else-if="!user" class="min-h-screen bg-background">
        <Header @menu-click="isAccountModalOpen = true" />
        <div class="mx-auto max-w-lg px-4 py-10 text-center">
            <h1 class="mb-2 text-xl font-bold text-foreground">Log in to checkout</h1>
            <p class="mb-6 text-sm text-muted-foreground">You need an account to place an order.</p>
            <button
                type="button"
                class="rounded-xl bg-orange-500 px-6 py-3 text-sm font-bold text-white hover:bg-orange-600"
                @click="isAccountModalOpen = true"
            >
                Log in / Register
            </button>
        </div>
        <MyAccountModal :is-open="isAccountModalOpen" @close="onLoginModalClose" />
    </div>

    <div v-else-if="items.length > 0" class="min-h-screen bg-background pb-28 lg:pb-8">
        <Header @menu-click="isAccountModalOpen = true" />

        <div class="mx-auto max-w-5xl px-3 py-4 sm:px-4">
            <button
                type="button"
                class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
                @click="goBack"
            >
                <ArrowLeft class="size-4" />
                Back to menu
            </button>

            <form class="grid gap-4 lg:grid-cols-[1fr_340px] lg:gap-6" @submit.prevent="handleSubmit">
                <div class="space-y-3">
                    <!-- Delivery type -->
                    <section class="rounded-2xl border border-border bg-card p-3 sm:p-4">
                        <div class="grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition"
                                :class="
                                    deliveryType === 'delivery'
                                        ? 'bg-orange-500 text-white shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="setDeliveryType('delivery')"
                            >
                                <Bike class="size-4" />
                                Delivery
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition"
                                :class="
                                    deliveryType === 'collection'
                                        ? 'bg-orange-500 text-white shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="setDeliveryType('collection')"
                            >
                                <ShoppingBag class="size-4" />
                                Collection
                            </button>
                        </div>
                    </section>

                    <!-- Contact -->
                    <section class="space-y-3 rounded-2xl border border-border bg-card p-3 sm:p-4">
                        <h2 class="text-sm font-bold text-foreground">Your details</h2>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <User class="size-3.5 text-orange-500" />
                                    Name
                                </label>
                                <input
                                    v-model="customerName"
                                    type="text"
                                    required
                                    autocomplete="name"
                                    :class="fieldClass"
                                    placeholder="Your name"
                                />
                            </div>
                            <div>
                                <label class="mb-1 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <Phone class="size-3.5 text-orange-500" />
                                    Phone
                                </label>
                                <input
                                    v-model="phoneNumber"
                                    type="tel"
                                    required
                                    autocomplete="tel"
                                    :class="fieldClass"
                                    placeholder="087 123 4567"
                                />
                            </div>
                        </div>

                        <template v-if="deliveryType === 'delivery'">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div class="sm:col-span-1">
                                    <label class="mb-1 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                        <MapPin class="size-3.5 text-orange-500" />
                                        Area code
                                    </label>
                                    <input
                                        v-model="areaCode"
                                        type="text"
                                        required
                                        maxlength="20"
                                        autocomplete="postal-code"
                                        :class="fieldClass"
                                        placeholder="V94"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs font-medium text-muted-foreground">
                                        Address
                                    </label>
                                    <input
                                        v-model="address"
                                        type="text"
                                        required
                                        autocomplete="street-address"
                                        :class="fieldClass"
                                        placeholder="Street, house / apartment"
                                        @input="onAddressInput"
                                    />
                                </div>
                            </div>
                        </template>
                    </section>

                    <!-- Time -->
                    <section class="rounded-2xl border border-border bg-card p-3 sm:p-4">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-orange-500/10">
                                <Clock class="size-4 text-orange-500" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-sm font-bold text-foreground">
                                    {{ deliveryType === 'delivery' ? 'Delivery time' : 'Collection time' }}
                                </h2>
                                <p class="truncate text-xs text-muted-foreground">{{ formatDateTime() }}</p>
                            </div>
                        </div>

                        <div class="mb-3 grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
                            <button
                                v-for="opt in dateOptions"
                                :key="opt.value"
                                type="button"
                                class="rounded-lg py-2.5 text-sm font-semibold transition"
                                :class="
                                    selectedDate === opt.value
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="selectedDate = opt.value"
                            >
                                {{ opt.label }}
                                <span class="ml-1 text-xs font-normal opacity-70">{{ opt.short }}</span>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <button
                                v-if="asapSlot"
                                type="button"
                                class="w-full rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                                :class="
                                    selectedTime === 'ASAP'
                                        ? 'bg-orange-500 text-white shadow-sm'
                                        : 'border border-border bg-background text-foreground hover:bg-muted'
                                "
                                @click="selectedTime = 'ASAP'"
                            >
                                ASAP
                                <span
                                    class="ml-1 text-xs font-normal"
                                    :class="selectedTime === 'ASAP' ? 'text-white/80' : 'text-muted-foreground'"
                                >
                                    (~{{ PREP_MINUTES }} min)
                                </span>
                            </button>

                            <p v-if="laterSlots.length" class="text-xs font-medium text-muted-foreground">
                                {{ selectedDateOption?.isToday ? 'Later today' : 'Choose a time' }}
                            </p>

                            <div
                                v-if="laterSlots.length"
                                class="max-h-56 overflow-y-auto rounded-xl border border-border bg-muted/30 p-1.5"
                            >
                                <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                                    <button
                                        v-for="slot in laterSlots"
                                        :key="slot"
                                        type="button"
                                        class="rounded-lg px-2 py-2 text-sm font-semibold transition"
                                        :class="
                                            selectedTime === slot
                                                ? 'bg-orange-500 text-white shadow-sm'
                                                : 'bg-background text-foreground hover:bg-muted'
                                        "
                                        @click="selectedTime = slot"
                                    >
                                        {{ slot }}
                                    </button>
                                </div>
                            </div>

                            <p
                                v-else-if="!asapSlot"
                                class="rounded-xl border border-border bg-muted/40 px-3 py-3 text-center text-xs text-muted-foreground"
                            >
                                No times left for this day — try Tomorrow.
                            </p>
                        </div>
                    </section>

                    <!-- Notes + payment -->
                    <section class="space-y-3 rounded-2xl border border-border bg-card p-3 sm:p-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">
                                Order notes <span class="opacity-70">(optional)</span>
                            </label>
                            <input
                                v-model="description"
                                type="text"
                                :class="fieldClass"
                                placeholder="e.g. No onions, leave at door"
                            />
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-medium text-muted-foreground">Pay with</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="flex h-11 items-center justify-center gap-2 rounded-xl border-2 text-sm font-semibold transition"
                                    :class="
                                        paymentMethod === 'cash'
                                            ? 'border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400'
                                            : 'border-border text-muted-foreground hover:border-orange-500/40'
                                    "
                                    @click="paymentMethod = 'cash'"
                                >
                                    € Cash
                                </button>
                                <button
                                    type="button"
                                    class="flex h-11 items-center justify-center gap-2 rounded-xl border-2 text-sm font-semibold transition"
                                    :class="
                                        paymentMethod === 'online'
                                            ? 'border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400'
                                            : 'border-border text-muted-foreground hover:border-orange-500/40'
                                    "
                                    @click="paymentMethod = 'online'"
                                >
                                    <CreditCard class="size-4" />
                                    Card
                                </button>
                            </div>
                        </div>
                    </section>

                    <p v-if="error" class="rounded-xl border border-red-500/40 bg-red-500/10 px-3 py-2.5 text-sm text-red-600 dark:text-red-300">
                        {{ error }}
                    </p>
                </div>

                <!-- Summary -->
                <aside class="h-fit rounded-2xl border border-border bg-card p-4 lg:sticky lg:top-20">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-bold text-foreground">Order summary</h2>
                        <button
                            type="button"
                            class="text-xs font-semibold text-orange-500"
                            @click="showItems = !showItems"
                        >
                            {{ showItems ? 'Hide' : 'Show' }} items
                        </button>
                    </div>

                    <div v-if="showItems" class="mb-3 max-h-40 space-y-1.5 overflow-y-auto border-b border-border pb-3">
                        <div v-for="(item, index) in items" :key="index" class="flex justify-between gap-2 text-sm">
                            <span class="min-w-0 text-foreground">
                                <span class="font-semibold text-orange-500">{{ item.quantity }}×</span>
                                {{ item.name }}
                            </span>
                            <span class="shrink-0 tabular-nums text-muted-foreground"
                                >€{{ Number(item.totalPrice).toFixed(2) }}</span
                            >
                        </div>
                    </div>

                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums text-foreground">€{{ subtotal.toFixed(2) }}</span>
                        </div>
                        <div v-if="discountAmount > 0" class="flex justify-between text-green-600 dark:text-green-400">
                            <span>Discount</span>
                            <span class="tabular-nums">-€{{ discountAmount.toFixed(2) }}</span>
                        </div>
                        <div v-if="deliveryType === 'delivery'" class="flex justify-between">
                            <span class="text-muted-foreground">Delivery</span>
                            <span
                                class="tabular-nums font-medium"
                                :class="deliveryFee === 0 ? 'text-green-500' : 'text-foreground'"
                            >
                                {{ deliveryFee === 0 ? 'Free' : `€${deliveryFee.toFixed(2)}` }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Service fee</span>
                            <span class="tabular-nums text-foreground">€{{ serviceFee.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div
                        v-if="unmetDiscounts.length"
                        class="mt-3 rounded-xl bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                    >
                        <p v-for="(d, i) in unmetDiscounts" :key="i">
                            Spend €{{ d.amountNeeded.toFixed(2) }} more for
                            {{ discountDisplayValue(d.discount) }} off
                        </p>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-border pt-3">
                        <span class="font-bold text-foreground">Total</span>
                        <span class="text-xl font-bold tabular-nums text-orange-500"
                            >€{{ total.toFixed(2) }}</span
                        >
                    </div>

                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="mt-4 hidden h-12 w-full rounded-xl bg-orange-500 text-sm font-bold text-white shadow-md shadow-orange-500/20 transition hover:bg-orange-600 disabled:opacity-50 lg:block"
                    >
                        {{ isSubmitting ? 'Placing order…' : `Place order · €${total.toFixed(2)}` }}
                    </button>

                    <p class="mt-3 hidden text-center text-[11px] leading-relaxed text-muted-foreground lg:block">
                        By ordering you agree to our
                        <Link href="/terms-of-use" class="text-orange-500 hover:underline">terms</Link>
                        and
                        <Link href="/privacy-statement" class="text-orange-500 hover:underline">privacy</Link>.
                    </p>
                </aside>

                <!-- Mobile sticky CTA -->
                <div
                    class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 p-3 backdrop-blur lg:hidden"
                >
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="h-12 w-full rounded-xl bg-orange-500 text-sm font-bold text-white shadow-lg shadow-orange-500/25 disabled:opacity-50"
                    >
                        {{ isSubmitting ? 'Placing order…' : `Place order · €${total.toFixed(2)}` }}
                    </button>
                </div>
            </form>
        </div>

        <MyAccountModal :is-open="isAccountModalOpen" @close="onAccountModalClose" />
    </div>
</template>
