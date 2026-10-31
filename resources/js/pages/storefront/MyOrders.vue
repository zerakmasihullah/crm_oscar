<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronLeft, Clock, MapPin, Package, Phone, ShoppingBag } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import Header from '@/components/storefront/Header.vue';
import MyAccountModal from '@/components/storefront/MyAccountModal.vue';
import StorefrontSeo from '@/components/storefront/StorefrontSeo.vue';
import { useStorefrontAuth } from '@/composables/storefront/useStorefrontAuth';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import { getCustomerOrders } from '@/lib/storefront/api';

defineOptions({ layout: StorefrontLayout });

const auth = useStorefrontAuth();
const user = computed(() => auth.user.value);
const orders = ref<Record<string, unknown>[]>([]);
const loading = ref(true);
const isAccountModalOpen = ref(false);

async function fetchOrders(customerId: number) {
    try {
        loading.value = true;
        const ordersData = await getCustomerOrders(customerId);
        const sortedOrders = ordersData.sort((a: Record<string, unknown>, b: Record<string, unknown>) => {
            const dateA = new Date(String(a.created_at)).getTime();
            const dateB = new Date(String(b.created_at)).getTime();
            return dateB - dateA;
        });
        orders.value = sortedOrders;
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    auth.syncFromStorage();
    if (!user.value) {
        router.visit('/');
        return;
    }
    void fetchOrders(user.value.id);
});

watch(isAccountModalOpen, (open) => {
    if (!open) {
        auth.syncFromStorage();
        if (user.value) {
            void fetchOrders(user.value.id);
        } else {
            router.visit('/');
        }
    }
});

function getStatusBadge(status: number) {
    const statusMap: Record<number, { label: string; bgColor: string; textColor: string }> = {
        1: { label: 'Pending', bgColor: 'bg-yellow-500/10', textColor: 'text-yellow-500' },
        2: { label: 'Accepted', bgColor: 'bg-green-500/10', textColor: 'text-green-500' },
        3: { label: 'Rejected', bgColor: 'bg-red-500/10', textColor: 'text-red-500' },
    };
    const statusInfo = statusMap[status] || {
        label: 'Unknown',
        bgColor: 'bg-gray-500/10',
        textColor: 'text-gray-500',
    };
    return statusInfo;
}

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
    <StorefrontSeo title="My Orders" path="/my-orders" :no-index="true" />

    <div v-if="user" class="min-h-screen bg-background transition-colors duration-300">
        <div class="sticky top-0 z-50">
            <Header @menu-click="isAccountModalOpen = true" />
        </div>

        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
            <div class="mb-10">
                <div class="flex items-center gap-3 mb-1">
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-orange-500 transition"
                        @click="router.visit('/')"
                    >
                        <ChevronLeft class="w-5 h-5" />
                    </button>
                    <div class="flex items-center gap-2">
                        <ShoppingBag class="w-5 h-5 text-orange-500" />
                        <h1 class="text-2xl font-medium text-foreground">Orders</h1>
                    </div>
                </div>
                <p v-if="orders.length > 0" class="text-muted-foreground text-sm ml-8">
                    {{ orders.length }} {{ orders.length === 1 ? 'order' : 'orders' }}
                </p>
            </div>

            <div v-if="loading" class="flex items-center justify-center py-20">
                <div class="flex items-center gap-2 text-muted-foreground text-sm">
                    <div class="w-4 h-4 border-2 border-border border-t-orange-500 rounded-full animate-spin" />
                    Loading...
                </div>
            </div>
            <div v-else-if="orders.length === 0" class="flex flex-col items-center justify-center py-20">
                <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4 border border-border">
                    <Package class="w-8 h-8 text-muted-foreground" />
                </div>
                <h2 class="text-lg font-medium text-foreground mb-1">No orders</h2>
                <p class="text-muted-foreground text-sm mb-6">You haven&apos;t placed any orders yet.</p>
                <button
                    type="button"
                    class="bg-orange-500 text-white text-sm font-medium py-2.5 px-6 rounded-lg hover:bg-orange-600 transition dark:bg-orange-600"
                    @click="router.visit('/')"
                >
                    Browse Menu
                </button>
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="order in orders"
                    :key="String(order.id)"
                    class="bg-card rounded-lg border border-border p-5 hover:border-orange-500/50 transition-colors shadow-sm"
                >
                    <div class="flex items-start justify-between mb-4 pb-4 border-b border-border">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-medium text-foreground">Order #{{ order.id }}</h3>
                                <span
                                    class="text-xs font-medium px-2 py-0.5 rounded"
                                    :class="[
                                        getStatusBadge(Number(order.status)).bgColor,
                                        getStatusBadge(Number(order.status)).textColor,
                                    ]"
                                >
                                    {{ getStatusBadge(Number(order.status)).label }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-muted-foreground text-sm">
                                <Clock class="w-3.5 h-3.5 text-muted-foreground" />
                                {{ formatDate(String(order.created_at)) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-semibold text-foreground">
                                {{ formatPrice(order.total_price as string | number) }}
                            </div>
                            <div
                                v-if="order.discount && parseFloat(String(order.discount)) > 0"
                                class="text-xs text-green-500 mt-1"
                            >
                                Saved {{ formatPrice(order.discount as string | number) }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="order.items && (order.items as unknown[]).length > 0"
                        class="mb-4 pb-4 border-b border-border"
                    >
                        <div
                            v-for="(item, itemIdx) in order.items as Record<string, unknown>[]"
                            :key="itemIdx"
                            class="text-sm mb-2.5 last:mb-0"
                        >
                            <span class="text-orange-500 font-medium">{{ item.quantity }}x</span>
                            <span class="text-foreground"> {{ item.name }}</span>
                            <span v-if="item.size" class="text-muted-foreground">
                                ({{ (item.size as Record<string, string>).name }})
                            </span>
                            <div
                                v-if="item.attachments && (item.attachments as unknown[]).length > 0"
                                class="ml-5 mt-1.5 space-y-1"
                            >
                                <div
                                    v-for="(attachment, attIdx) in item.attachments as Record<string, unknown>[]"
                                    :key="attIdx"
                                    class="text-xs text-muted-foreground flex items-center gap-1.5"
                                >
                                    <span class="w-1 h-1 rounded-full bg-muted-foreground" />
                                    <span v-if="Number(attachment.quantity) > 1" class="text-muted-foreground/70">
                                        {{ attachment.quantity }}x
                                    </span>
                                    {{ attachment.name }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="order.address || order.area_code || order.phone_number"
                        class="mb-4 pb-4 border-b border-border space-y-2 text-sm"
                    >
                        <div v-if="order.address" class="flex items-start gap-2 text-foreground">
                            <MapPin class="w-4 h-4 text-orange-500 mt-0.5 flex-shrink-0" />
                            <span>{{ order.address }}</span>
                        </div>
                        <div v-if="order.area_code" class="text-muted-foreground ml-6">
                            Area Code: <span class="text-foreground">{{ order.area_code }}</span>
                        </div>
                        <div v-if="order.phone_number" class="flex items-center gap-2 text-foreground ml-6">
                            <Phone class="w-4 h-4 text-orange-500" />
                            {{ order.phone_number }}
                        </div>
                    </div>

                    <div v-if="order.payment_method" class="text-sm text-muted-foreground">
                        Payment:
                        <span class="text-foreground capitalize font-medium">{{ order.payment_method }}</span>
                    </div>
                </div>
            </div>
        </div>

        <MyAccountModal :is-open="isAccountModalOpen" @close="isAccountModalOpen = false" />
    </div>
</template>
