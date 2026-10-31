<script setup lang="ts">
import { ChevronLeft, ChevronRight, LayoutGrid } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import StorefrontImage from '@/components/storefront/StorefrontImage.vue';

interface Category {
    id: string;
    label: string;
    image?: string;
    count?: number;
}

const props = defineProps<{
    categories: Category[];
    activeCategory: string;
    setActiveCategory: (id: string) => void;
    onListClick?: () => void;
}>();

const DEFAULT_IMAGE = '/brand/menu-default.jpg';
const scrollContainerRef = ref<HTMLDivElement | null>(null);
const buttonRefs = ref<Map<string, HTMLButtonElement>>(new Map());

function setButtonRef(id: string, el: Element | null) {
    if (el instanceof HTMLButtonElement) {
        buttonRefs.value.set(id, el);
    } else {
        buttonRefs.value.delete(id);
    }
}

function chipImage(category: Category) {
    return category.image || DEFAULT_IMAGE;
}

watch(
    () => props.activeCategory,
    async () => {
        await nextTick();
        const activeButton = buttonRefs.value.get(props.activeCategory);
        const container = scrollContainerRef.value;
        if (activeButton && container) {
            requestAnimationFrame(() => {
                const scrollLeft =
                    activeButton.offsetLeft - container.offsetWidth / 2 + activeButton.offsetWidth / 2;
                container.scrollTo({ left: scrollLeft, behavior: 'smooth' });
            });
        }
    },
);

function scroll(direction: 'left' | 'right') {
    if (scrollContainerRef.value) {
        scrollContainerRef.value.scrollBy({
            left: direction === 'left' ? -240 : 240,
            behavior: 'smooth',
        });
    }
}
</script>

<template>
    <div class="relative py-3 md:py-4 border-b border-border/80 bg-background/95 backdrop-blur-sm">
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="hidden lg:inline-flex items-center justify-center w-9 h-9 rounded-full border border-border bg-card text-muted-foreground hover:text-orange-500 hover:border-orange-500/40 transition shrink-0"
                aria-label="Scroll left"
                @click="scroll('left')"
            >
                <ChevronLeft class="w-4 h-4" />
            </button>

            <div class="relative flex-1 min-w-0">
                <div
                    class="pointer-events-none absolute inset-y-0 left-0 w-5 z-10 bg-gradient-to-r from-background to-transparent"
                />
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 w-5 z-10 bg-gradient-to-l from-background to-transparent"
                />

                <div
                    ref="scrollContainerRef"
                    class="flex gap-2 overflow-x-auto scrollbar-hide px-1 py-0.5 scroll-smooth"
                >
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        :ref="(el) => setButtonRef(category.id, el as Element | null)"
                        type="button"
                        :title="category.label"
                        class="inline-flex shrink-0 items-center gap-2 rounded-full py-1.5 pe-3 ps-1.5 text-sm font-medium whitespace-nowrap transition border"
                        :class="
                            activeCategory === category.id
                                ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-500/25'
                                : 'bg-muted/80 text-muted-foreground border-transparent hover:bg-muted hover:text-foreground'
                        "
                        @click="setActiveCategory(category.id)"
                    >
                        <StorefrontImage
                            :src="chipImage(category)"
                            :alt="category.label"
                            :eager="activeCategory === category.id"
                            :img-class="
                                activeCategory === category.id
                                    ? 'size-7 shrink-0 rounded-full object-cover ring-2 ring-white/40'
                                    : 'size-7 shrink-0 rounded-full object-cover ring-2 ring-background/80'
                            "
                        />
                        <span class="max-w-[9rem] truncate">{{ category.label }}</span>
                        <span
                            v-if="category.count"
                            class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
                            :class="
                                activeCategory === category.id
                                    ? 'bg-white/20 text-white'
                                    : 'bg-background/70 text-muted-foreground'
                            "
                        >
                            {{ category.count }}
                        </span>
                    </button>
                </div>
            </div>

            <button
                type="button"
                class="hidden lg:inline-flex items-center justify-center w-9 h-9 rounded-full border border-border bg-card text-muted-foreground hover:text-orange-500 hover:border-orange-500/40 transition shrink-0"
                aria-label="Scroll right"
                @click="scroll('right')"
            >
                <ChevronRight class="w-4 h-4" />
            </button>

            <button
                type="button"
                class="inline-flex items-center gap-1.5 h-9 px-3 rounded-full border border-border bg-card text-foreground hover:text-orange-500 hover:border-orange-500/40 transition shrink-0"
                aria-label="View all categories"
                @click="onListClick?.()"
            >
                <LayoutGrid class="w-4 h-4" />
                <span class="hidden sm:inline text-xs font-semibold">All</span>
            </button>
        </div>
    </div>
</template>
