<script setup lang="ts">
import { Info, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import StorefrontImage from '@/components/storefront/StorefrontImage.vue';
import { getMenuItemsByCategory, type MenuItem } from '@/lib/storefront/api';

function stripHtml(html: string | null | undefined): string {
    if (!html) return '';
    try {
        const tmp = document.createElement('div');
        tmp.innerHTML = String(html);
        return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
    } catch {
        return String(html || '');
    }
}

const props = defineProps<{
    categoryId: string;
    categoryLabel: string;
    categoryApiId?: number;
    searchQuery: string;
    onAddToBasket: (item: unknown) => void;
    onSelectProduct: (product: unknown) => void;
}>();

const items = ref<MenuItem[]>([]);
const loading = ref(true);

watch(
    () => props.categoryApiId,
    async (categoryApiId) => {
        if (categoryApiId) {
            loading.value = true;
            try {
                items.value = await getMenuItemsByCategory(categoryApiId);
            } catch {
                items.value = [];
            } finally {
                loading.value = false;
            }
        } else {
            loading.value = false;
            items.value = [];
        }
    },
    { immediate: true },
);

const filteredItems = computed(() =>
    items.value.filter((item) => {
        const cleanTitle = stripHtml(item.title).toLowerCase();
        const cleanDescription = item.description ? stripHtml(item.description).toLowerCase() : '';
        const query = props.searchQuery.toLowerCase();
        return cleanTitle.includes(query) || cleanDescription.includes(query);
    }),
);

function formatItemPrice(item: MenuItem): { formattedPrice: string; hasMultipleSizes: boolean } {
    const hasMultipleSizes = !!(item.size && item.size.length > 0);
    let priceValue = 0;

    if (hasMultipleSizes && item.size && item.size.length > 0) {
        const prices = item.size
            .map((size) => {
                if (typeof size.price === 'number') return size.price;
                if (typeof size.price === 'string') {
                    return parseFloat(size.price.replace(/[€\s,]/g, '')) || 0;
                }
                return 0;
            })
            .filter((p) => p > 0);
        if (prices.length > 0) priceValue = Math.min(...prices);
    } else if (item.price !== null && item.price !== undefined) {
        if (typeof item.price === 'number') priceValue = item.price;
        else priceValue = parseFloat(String(item.price).replace(/[€\s,]/g, '')) || 0;
    }

    return { formattedPrice: `€ ${priceValue.toFixed(2)}`, hasMultipleSizes };
}
</script>

<template>
    <div v-if="loading" class="py-4 content-visibility-auto">
        <h2 class="text-lg md:text-xl font-bold text-foreground mb-3">{{ categoryLabel }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
            <div v-for="n in 4" :key="n" class="h-28 rounded-2xl bg-card ring-1 ring-border/50 animate-pulse" />
        </div>
    </div>

    <div v-else-if="filteredItems.length > 0" class="py-3 md:py-4" style="content-visibility: auto; contain-intrinsic-size: auto 400px">
        <div class="flex items-center justify-between mb-3 md:mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-foreground tracking-tight">
                {{ categoryLabel }}
            </h2>
            <span
                class="text-muted-foreground text-xs font-medium bg-card px-2.5 py-1 rounded-full border border-border tabular-nums"
            >
                {{ filteredItems.length }} items
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 md:gap-3">
            <button
                v-for="item in filteredItems"
                :key="item.id"
                :data-product-id="item.id"
                type="button"
                class="group w-full bg-card rounded-2xl p-3 md:p-3.5 flex items-stretch gap-3 text-left hover:bg-muted/50 transition cursor-pointer"
                @click="onSelectProduct(item)"
            >
                <div class="flex-1 min-w-0 flex flex-col justify-center py-0.5">
                    <div class="flex items-center gap-1.5 mb-1">
                        <h3 class="text-foreground font-bold text-[15px] md:text-base leading-snug line-clamp-2">
                            {{ stripHtml(item.title) }}
                        </h3>
                        <span
                            class="inline-flex w-4 h-4 rounded-full bg-muted items-center justify-center shrink-0"
                            aria-hidden="true"
                        >
                            <Info class="w-2.5 h-2.5 text-muted-foreground" />
                        </span>
                    </div>
                    <p class="text-muted-foreground font-medium text-sm mb-1">
                        <span v-if="formatItemPrice(item).hasMultipleSizes">from </span>
                        {{ formatItemPrice(item).formattedPrice }}
                    </p>
                    <p
                        v-if="item.description"
                        class="text-muted-foreground/80 text-xs md:text-sm leading-relaxed line-clamp-2"
                    >
                        {{ stripHtml(item.description) }}
                    </p>
                </div>

                <div class="relative shrink-0 self-center">
                    <div class="w-[5.5rem] h-[5.5rem] md:w-28 md:h-28 rounded-2xl overflow-hidden bg-muted">
                        <StorefrontImage
                            :src="item.image"
                            :alt="stripHtml(item.title)"
                            img-class="w-full h-full object-cover transition duration-300 group-hover:scale-105"
                        />
                    </div>
                    <span
                        class="absolute -top-1.5 -right-1.5 w-8 h-8 rounded-full bg-orange-500 text-white flex items-center justify-center shadow-md group-hover:scale-110 transition"
                        aria-hidden="true"
                    >
                        <Plus class="w-4 h-4" :stroke-width="2.75" />
                    </span>
                </div>
            </button>
        </div>
    </div>
</template>
