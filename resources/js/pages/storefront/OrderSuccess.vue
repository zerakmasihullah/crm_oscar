<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Clock, Loader2, MapPin, Phone, ShoppingBag } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import Header from '@/components/storefront/Header.vue';
import StorefrontSeo from '@/components/storefront/StorefrontSeo.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import { getOrderBySession } from '@/lib/storefront/api';

defineOptions({ layout: StorefrontLayout });

const loading = ref(true);
const order = ref<Record<string, unknown> | null>(null);
const error = ref('');
const sessionId = ref<string | null>(null);
const isAccountModalOpen = ref(false);

async function fetchOrderDetails() {
    if (!sessionId.value) {
        return;
    }
    try {
        loading.value = true;
        order.value = await getOrderBySession(sessionId.value);
    } catch (err: unknown) {
        error.value =
            err instanceof Error
                ? err.message
                : 'Failed to load order details. Please check your orders page.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    localStorage.removeItem('basketItems');
    window.dispatchEvent(new Event('basketCleared'));

    sessionId.value = new URLSearchParams(window.location.search).get('session_id');

    if (!sessionId.value) {
        error.value = 'No session ID found';
        loading.value = false;
        return;
    }

    void fetchOrderDetails();
});

function formatDate(dateString: string) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatPrice(price: string | number) {
    const numPrice = typeof price === 'string' ? parseFloat(price) : price;
    return `€ ${numPrice.toFixed(2)}`;
}
</script>

<template>
    <StorefrontSeo title="Order Success" path="/order-success" :no-index="true" />

    <div class="min-h-screen bg-background">
        <Header @menu-click="isAccountModalOpen = true" />

        <div v-if="loading" class="flex items-center justify-center py-20">
            <div class="text-center">
                <Loader2 class="mx-auto mb-4 size-12 animate-spin text-orange-500" />
                <p class="text-sm text-muted-foreground">Loading order details…</p>
            </div>
        </div>

        <div v-else-if="error" class="mx-auto max-w-lg px-4 py-10">
            <div class="rounded-2xl border border-red-500/40 bg-red-500/10 p-6 text-center">
                <p class="mb-4 text-sm text-red-600 dark:text-red-300">{{ error }}</p>
                <button
                    type="button"
                    class="rounded-full bg-orange-500 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600"
                    @click="router.visit('/my-orders')"
                >
                    View my orders
                </button>
            </div>
        </div>

        <div v-else-if="order" class="mx-auto max-w-3xl px-4 py-8 sm:py-10">
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex size-20 items-center justify-center rounded-full bg-green-500/15"
                >
                    <CheckCircle class="size-12 text-green-500" />
                </div>
                <h1 class="mb-2 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                    Order placed successfully
                </h1>
                <p class="text-sm text-muted-foreground sm:text-base">
                    Thank you — we’ve received your order.
                </p>
            </div>

            <div class="mb-6 rounded-2xl border border-border bg-card p-5 sm:p-6">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0 flex-1 space-y-4">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold text-foreground">Order #{{ order.id }}</h2>
                                <span
                                    class="rounded-full border border-yellow-500/40 bg-yellow-500/15 px-2.5 py-0.5 text-xs font-medium text-yellow-600 dark:text-yellow-400"
                                >
                                    Pending
                                </span>
                            </div>
                            <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
                                <Clock class="size-4" />
                                {{ formatDate(String(order.created_at)) }}
                            </p>
                        </div>

                        <div
                            v-if="order.items && (order.items as unknown[]).length > 0"
                            class="space-y-2 border-t border-border pt-4"
                        >
                            <h3 class="mb-1 text-sm font-medium text-foreground">Items</h3>
                            <div
                                v-for="(item, itemIdx) in order.items as Record<string, unknown>[]"
                                :key="itemIdx"
                                class="text-sm"
                            >
                                <div class="text-foreground/90">
                                    {{ item.quantity }}x {{ item.name }}
                                    <template v-if="item.size">
                                        ({{ (item.size as Record<string, string>).name }})
                                    </template>
                                </div>
                                <div
                                    v-if="item.attachments && (item.attachments as unknown[]).length > 0"
                                    class="mt-1 ml-4 space-y-0.5"
                                >
                                    <div
                                        v-for="(attachment, attIdx) in item.attachments as Record<string, unknown>[]"
                                        :key="attIdx"
                                        class="text-xs text-muted-foreground"
                                    >
                                        <template v-if="Number(attachment.quantity) > 1">
                                            {{ attachment.quantity }}x
                                        </template>
                                        {{ attachment.name }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="order.address || order.area_code || order.phone_number"
                            class="space-y-2 border-t border-border pt-4"
                        >
                            <div v-if="order.address" class="flex items-start gap-2 text-sm">
                                <MapPin class="mt-0.5 size-4 shrink-0 text-orange-500" />
                                <span class="text-foreground/90">{{ order.address }}</span>
                            </div>
                            <div v-if="order.area_code" class="text-sm text-muted-foreground">
                                Area code:
                                <span class="text-foreground/90">{{ order.area_code }}</span>
                            </div>
                            <div v-if="order.phone_number" class="flex items-center gap-2 text-sm">
                                <Phone class="size-4 text-orange-500" />
                                <span class="text-foreground/90">{{ order.phone_number }}</span>
                            </div>
                        </div>

                        <div v-if="order.payment_method" class="border-t border-border pt-4 text-sm">
                            <span class="text-muted-foreground">Payment: </span>
                            <span class="capitalize text-foreground/90">{{ order.payment_method }}</span>
                            <span class="ml-2 text-green-600 dark:text-green-400">✓ Paid</span>
                        </div>
                    </div>

                    <div class="md:text-right">
                        <div class="text-3xl font-bold text-foreground">
                            {{ formatPrice(order.total_price as string | number) }}
                        </div>
                        <div
                            v-if="order.discount && parseFloat(String(order.discount)) > 0"
                            class="text-sm text-green-600 dark:text-green-400"
                        >
                            Discount: {{ formatPrice(order.discount as string | number) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <button
                    type="button"
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-orange-500 py-3 px-6 text-sm font-semibold text-white transition hover:bg-orange-600"
                    @click="router.visit('/my-orders')"
                >
                    <ShoppingBag class="size-5" />
                    View all orders
                </button>
                <button
                    type="button"
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-border bg-card py-3 px-6 text-sm font-semibold text-foreground transition hover:bg-muted"
                    @click="router.visit('/')"
                >
                    <ArrowLeft class="size-5" />
                    Continue shopping
                </button>
            </div>

            <div class="mt-6 rounded-2xl border border-orange-500/30 bg-orange-500/10 p-4">
                <p class="text-sm text-foreground/90">
                    <strong class="text-orange-600 dark:text-orange-400">What’s next?</strong>
                    We’ll email a confirmation shortly. Your order is being prepared and you’ll get status updates.
                </p>
            </div>
        </div>
    </div>
</template>
