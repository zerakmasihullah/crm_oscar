<script setup lang="ts">
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import { ref } from 'vue';
import StorefrontImage from '@/components/storefront/StorefrontImage.vue';
import type { MenuItem } from '@/lib/storefront/api';

function stripHtml(html: string | null | undefined): string {
    if (!html) return '';
    try {
        const tmp = document.createElement('div');
        tmp.innerHTML = String(html);
        return (tmp.textContent || tmp.innerText || '')
            .replace(/\s+/g, ' ')
            .trim();
    } catch {
        return String(html || '');
    }
}

defineProps<{
    items: MenuItem[];
    onSelectProduct: (item: MenuItem) => void;
}>();

const scrollContainerRef = ref<HTMLDivElement | null>(null);

function scroll(direction: 'left' | 'right') {
    scrollContainerRef.value?.scrollBy({
        left: direction === 'left' ? -280 : 280,
        behavior: 'smooth',
    });
}

function formatPrice(item: MenuItem): string {
    if (item.price === null || item.price === undefined) return '€ 0.00';
    if (typeof item.price === 'number') return `€ ${item.price.toFixed(2)}`;
    const priceStr = String(item.price);
    return priceStr.startsWith('€') ? priceStr.replace('€', '€ ') : `€ ${priceStr}`;
}
</script>

<template>
    <div class="py-4 md:py-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="text-xl md:text-2xl font-bold text-foreground tracking-tight">Highlights</h2>
            <div class="hidden sm:flex items-center gap-1">
                <button
                    type="button"
                    class="w-8 h-8 rounded-full border border-border text-muted-foreground hover:text-orange-500 hover:border-orange-500/40 transition inline-flex items-center justify-center"
                    @click="scroll('left')"
                >
                    <ChevronLeft class="w-4 h-4" />
                </button>
                <button
                    type="button"
                    class="w-8 h-8 rounded-full border border-border text-muted-foreground hover:text-orange-500 hover:border-orange-500/40 transition inline-flex items-center justify-center"
                    @click="scroll('right')"
                >
                    <ChevronRight class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div
            ref="scrollContainerRef"
            class="flex gap-2.5 overflow-x-auto scrollbar-hide scroll-smooth"
        >
            <button
                v-for="item in items"
                :key="item.id"
                :data-product-id="item.id"
                type="button"
                class="group flex-shrink-0 w-[17rem] sm:w-[19rem] flex items-stretch gap-3 rounded-2xl bg-muted/40 hover:bg-muted/70 p-3 text-left transition"
                @click="onSelectProduct(item)"
            >
                <div class="flex-1 min-w-0 flex flex-col justify-center py-0.5">
                    <p
                        v-if="item.menu"
                        class="text-[10px] uppercase tracking-wide text-muted-foreground mb-0.5 truncate"
                    >
                        {{ stripHtml(item.menu.title || item.menu.name) }}
                    </p>
                    <h3 class="text-foreground font-bold text-sm leading-snug line-clamp-2">
                        {{ stripHtml(item.title) }}
                    </h3>
                    <p class="mt-1.5 text-orange-500 font-semibold text-sm tabular-nums">
                        {{ formatPrice(item) }}
                    </p>
                </div>

                <div class="relative shrink-0 self-center">
                    <div class="w-[4.75rem] h-[4.75rem] sm:w-24 sm:h-24 rounded-2xl overflow-hidden bg-muted">
                        <StorefrontImage
                            :src="item.image"
                            :alt="stripHtml(item.title)"
                            img-class="w-full h-full object-cover transition duration-300 group-hover:scale-105"
                        />
                    </div>
                    <span
                        class="absolute -top-1.5 -right-1.5 w-7 h-7 rounded-full bg-orange-500 text-white flex items-center justify-center shadow-md group-hover:scale-110 transition"
                        aria-hidden="true"
                    >
                        <Plus class="w-3.5 h-3.5" :stroke-width="2.75" />
                    </span>
                </div>
            </button>
        </div>
    </div>
</template>
