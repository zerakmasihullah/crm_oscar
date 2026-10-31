<script setup lang="ts">
import { Clock, Tag, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { getActiveDiscounts, type DiscountData } from '@/lib/storefront/api';

const discounts = ref<DiscountData[]>([]);
const currentDiscount = ref<DiscountData | null>(null);
const isVisible = ref(true);

onMounted(async () => {
    try {
        const activeDiscounts = await getActiveDiscounts();
        discounts.value = activeDiscounts;

        if (activeDiscounts.length > 0) {
            const now = new Date();
            const dayOfWeek = now.toLocaleDateString('en-US', { weekday: 'long' });

            for (const discount of activeDiscounts) {
                let isValidDay = true;
                if (discount.days_of_week && discount.days_of_week.length > 0) {
                    const days = discount.days_of_week.map((d) => d.toLowerCase());
                    if (!days.includes(dayOfWeek.toLowerCase())) {
                        isValidDay = false;
                    }
                }

                if (isValidDay) {
                    currentDiscount.value = discount;
                    break;
                }
            }
        }
    } catch (error) {
        console.error('Failed to fetch discounts:', error);
    }
});

const discountValue = computed(() => {
    if (!currentDiscount.value) return 0;
    return typeof currentDiscount.value.discount_value === 'number'
        ? currentDiscount.value.discount_value
        : parseFloat(String(currentDiscount.value.discount_value)) || 0;
});

const discountDisplay = computed(() => {
    if (!currentDiscount.value) return '';
    return currentDiscount.value.discount_type === 'percentage'
        ? `${discountValue.value}%`
        : `€${discountValue.value.toFixed(2)}`;
});

const discountDescription = computed(() => {
    if (!currentDiscount.value) return '';
    const parts: string[] = [];

    if (currentDiscount.value.days_of_week && currentDiscount.value.days_of_week.length > 0) {
        const days = currentDiscount.value.days_of_week.map(
            (d) => d.charAt(0).toUpperCase() + d.slice(1).toLowerCase(),
        );
        if (days.length === 7) {
            parts.push('Every day');
        } else {
            parts.push(days.join(', '));
        }
    } else {
        parts.push('Every day');
    }

    if (currentDiscount.value.minimum_purchase_amount && currentDiscount.value.minimum_purchase_amount > 0) {
        const minAmount =
            typeof currentDiscount.value.minimum_purchase_amount === 'number'
                ? currentDiscount.value.minimum_purchase_amount
                : parseFloat(String(currentDiscount.value.minimum_purchase_amount)) || 0;
        if (minAmount > 0) {
            parts.push(`Min. purchase: €${minAmount.toFixed(2)}`);
        }
    }

    return parts.join(' • ');
});
</script>

<template>
    <div v-if="isVisible && currentDiscount" class="bg-gradient-to-r from-orange-500 to-orange-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between py-3.5">
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <Tag class="w-5 h-5 flex-shrink-0 opacity-90" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-3 flex-wrap">
                            <span class="font-bold text-lg sm:text-xl tracking-tight"> {{ discountDisplay }} OFF </span>
                            <span class="text-sm sm:text-base font-medium text-white/95">
                                {{ currentDiscount.name }}
                            </span>
                        </div>
                        <div v-if="discountDescription" class="flex items-center gap-1.5 mt-1.5">
                            <Clock class="w-3.5 h-3.5 flex-shrink-0 opacity-75" />
                            <span class="text-xs sm:text-sm text-white/85 font-normal">
                                {{ discountDescription }}
                            </span>
                        </div>
                    </div>
                </div>
                <button
                    type="button"
                    class="flex-shrink-0 p-1.5 hover:bg-white/10 rounded-md transition-colors duration-200"
                    aria-label="Close discount banner"
                    @click="isVisible = false"
                >
                    <X class="w-4 h-4 sm:w-5 sm:h-5" />
                </button>
            </div>
        </div>
    </div>
</template>
