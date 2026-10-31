<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Bike,
    Building2,
    Clock,
    Home as HomeIcon,
    Info,
    Mail,
    MapPin,
    Phone,
    Receipt,
    Search,
    ShoppingBag,
    Star,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    watch,
    type ComponentPublicInstance,
} from 'vue';
import BasketSidebar from '@/components/storefront/BasketSidebar.vue';
import CategoriesModal from '@/components/storefront/CategoriesModal.vue';
import DiscountBanner from '@/components/storefront/DiscountBanner.vue';
import DownloadAppSection from '@/components/storefront/DownloadAppSection.vue';
import Header from '@/components/storefront/Header.vue';
import HeroSection from '@/components/storefront/HeroSection.vue';
import HighlightsCarousel from '@/components/storefront/HighlightsCarousel.vue';
import JsonLd from '@/components/storefront/JsonLd.vue';
import MenuCategories from '@/components/storefront/MenuCategories.vue';
import MenuSection from '@/components/storefront/MenuSection.vue';
import MobileBasket from '@/components/storefront/MobileBasket.vue';
import MyAccountModal from '@/components/storefront/MyAccountModal.vue';
import ProductModal from '@/components/storefront/ProductModal.vue';
import StorefrontImage from '@/components/storefront/StorefrontImage.vue';
import StorefrontSeo from '@/components/storefront/StorefrontSeo.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import {
    useStorefrontBasket,
    type StorefrontBasketItem,
} from '@/composables/storefront/useStorefrontBasket';
import {
    getAllMenuItems,
    getFees,
    getMenuCategories,
    getPopularMenuItems,
    searchMenuItems,
    type FeesData,
    type Menu,
    type MenuItem,
} from '@/lib/storefront/api';
import { getImageUrl } from '@/lib/storefront/image-utils';
import { allHomeJsonLd } from '@/lib/storefront/json-ld';

defineOptions({ layout: StorefrontLayout });

const page = usePage();
const basket = useStorefrontBasket();
const basketItems = basket.itemsRef;

function getIrelandDateString(): string {
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Europe/Dublin',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    });
    const parts = formatter.formatToParts(now);
    const year = parseInt(parts.find((p) => p.type === 'year')?.value || '0');
    const month = parseInt(parts.find((p) => p.type === 'month')?.value || '0');
    const day = parseInt(parts.find((p) => p.type === 'day')?.value || '0');
    return `${year}-${month}-${day}`;
}

function stripHtml(html: string | null | undefined): string {
    if (!html) return '';
    try {
        const htmlStr = String(html);
        const tmp = document.createElement('div');
        tmp.innerHTML = htmlStr;
        let text = tmp.textContent || tmp.innerText || '';
        text = text
            .replace(/&nbsp;/g, ' ')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'")
            .replace(/&apos;/g, "'")
            .replace(/\s+/g, ' ')
            .trim();
        return text;
    } catch {
        return String(html || '');
    }
}

const activeCategory = ref('highlights');
const searchQuery = ref('');
const selectedProduct = ref<MenuItem | null>(null);
const menuCategories = ref<Menu[]>([]);
const allMenuItems = ref<MenuItem[]>([]);
const highlightItems = ref<MenuItem[]>([]);
const searchResults = ref<MenuItem[]>([]);
const isSearching = ref(false);
const loading = ref(true);
const apiError = ref(false);
const isInfoModalOpen = ref(false);
const isScrolled = ref(false);
const isAccountModalOpen = ref(false);
const isCategoriesModalOpen = ref(false);
const isRestaurantClosedModalOpen = ref(false);
const headerHeight = ref(64);
const stickySearchHeight = ref(0);
const feesData = ref<FeesData | null>(null);

const sectionRefs = ref<Record<string, HTMLElement | null>>({});
const heroRef = ref<HTMLElement | null>(null);
const restaurantInfoRef = ref<HTMLElement | null>(null);
const headerRef = ref<HTMLElement | null>(null);
const stickySearchRef = ref<HTMLElement | null>(null);
const isScrollingProgrammatically = ref(false);

function setSectionRef(id: string, el: Element | ComponentPublicInstance | null) {
    sectionRefs.value[id] = el instanceof HTMLElement ? el : null;
}

const deliveryHours = [
    { day: 'Monday', time: '15:00 - 00:30' },
    { day: 'Tuesday', time: '15:00 - 00:30' },
    { day: 'Wednesday', time: '15:00 - 00:30' },
    { day: 'Thursday', time: '15:00 - 00:30' },
    { day: 'Friday', time: '15:00 - 01:30' },
    { day: 'Saturday', time: '15:00 - 01:30' },
    { day: 'Sunday', time: '15:00 - 01:30' },
];

function updateHeights() {
    if (headerRef.value) headerHeight.value = headerRef.value.offsetHeight;
    if (stickySearchRef.value) stickySearchHeight.value = stickySearchRef.value.offsetHeight;
}

function dismissClosedModal() {
    isRestaurantClosedModalOpen.value = false;
    localStorage.setItem('restaurantClosedModalDismissed', getIrelandDateString());
}

let heightTimeoutId: ReturnType<typeof setTimeout> | null = null;
let scrollCategoryTimeout: ReturnType<typeof setTimeout> | null = null;

const handleScroll = () => {
    if (restaurantInfoRef.value) {
        const rect = restaurantInfoRef.value.getBoundingClientRect();
        isScrolled.value = rect.bottom <= headerHeight.value;
    }
};

const handleScrollCategory = () => {
    if (isScrollingProgrammatically.value) return;
    if (scrollCategoryTimeout) clearTimeout(scrollCategoryTimeout);
    scrollCategoryTimeout = setTimeout(() => {
        let activeSectionId: string | null = null;
        const scrollPosition = window.scrollY;
        const headerOffset = headerHeight.value + stickySearchHeight.value + 20;
        const thresholdTop = scrollPosition + headerOffset;
        for (let i = categories.value.length - 1; i >= 0; i--) {
            const category = categories.value[i];
            const section = sectionRefs.value[category.id];
            if (section && thresholdTop >= section.offsetTop) {
                activeSectionId = category.id;
                break;
            }
        }
        if (activeSectionId && activeSectionId !== activeCategory.value) {
            activeCategory.value = activeSectionId;
        }
    }, 150);
};

onMounted(() => {
    requestAnimationFrame(updateHeights);
    heightTimeoutId = setTimeout(updateHeights, 100);
    window.addEventListener('resize', updateHeights);
    basket.reload();

    void (async () => {
        try {
            const now = new Date();
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Europe/Dublin',
                hour: 'numeric',
                minute: 'numeric',
                hour12: false,
            });
            const parts = formatter.formatToParts(now);
            const hour = parseInt(parts.find((p) => p.type === 'hour')?.value || '0');
            const minute = parseInt(parts.find((p) => p.type === 'minute')?.value || '0');
            if (hour * 60 + minute < 15 * 60) {
                const lastDismissed = localStorage.getItem('restaurantClosedModalDismissed');
                if (lastDismissed !== getIrelandDateString()) {
                    isRestaurantClosedModalOpen.value = true;
                }
            }
        } catch {
            // ignore timezone failures
        }

        loading.value = true;
        apiError.value = false;
        try {
            const [fees, categories, items, popularItems] = await Promise.all([
                getFees(),
                getMenuCategories(),
                getAllMenuItems(),
                getPopularMenuItems(),
            ]);
            feesData.value = fees;
            if (categories.length > 0) menuCategories.value = categories;
            if (items.length > 0) allMenuItems.value = items;
            if (popularItems.length > 0) highlightItems.value = popularItems.slice(0, 4);
        } catch {
            apiError.value = true;
        } finally {
            loading.value = false;
            requestAnimationFrame(updateHeights);
        }
    })();

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
    window.addEventListener('scroll', handleScrollCategory, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('resize', updateHeights);
    if (heightTimeoutId) clearTimeout(heightTimeoutId);
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('scroll', handleScrollCategory);
});

watch(
    () => page.url,
    (url) => {
        if (url.split('?')[0] === '/') basket.reload();
    },
);

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(searchQuery, (q) => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(async () => {
        if (q.trim().length > 2) {
            isSearching.value = true;
            try {
                searchResults.value = await searchMenuItems(q);
            } catch {
                searchResults.value = [];
            } finally {
                isSearching.value = false;
            }
        } else {
            searchResults.value = [];
        }
    }, 500);
});

const DEFAULT_CATEGORY_IMAGE = '/brand/menu-default.jpg';

function categoryImage(menu?: Menu | null, fallbackItem?: MenuItem | null): string {
    const fromMenu = getImageUrl(menu?.image);
    if (fromMenu) return fromMenu;
    const fromItem = getImageUrl(fallbackItem?.image);
    if (fromItem) return fromItem;
    return DEFAULT_CATEGORY_IMAGE;
}

const categories = computed(() => {
    const categoryMap = new Map<
        string,
        { id: string; label: string; apiId: number | undefined; image: string; count?: number }
    >();
    categoryMap.set('highlights', {
        id: 'highlights',
        label: 'Highlights',
        apiId: undefined,
        image: categoryImage(null, highlightItems.value[0] ?? null),
        count: highlightItems.value.length || undefined,
    });
    const sortedMenuCategories = [...menuCategories.value].sort((a, b) => {
        const aHasDeal = (a.title?.toLowerCase() || a.name?.toLowerCase() || '').includes('deal');
        const bHasDeal = (b.title?.toLowerCase() || b.name?.toLowerCase() || '').includes('deal');
        if (aHasDeal && !bHasDeal) return -1;
        if (!aHasDeal && bHasDeal) return 1;
        return 0;
    });
    sortedMenuCategories
        .filter((menu) => {
            const title = menu.title?.toLowerCase() || menu.name?.toLowerCase() || '';
            return title !== 'highlights';
        })
        .forEach((menu) => {
            const baseId =
                menu.title?.toLowerCase().replace(/\s+/g, '-') ||
                menu.name?.toLowerCase().replace(/\s+/g, '-') ||
                `menu-${menu.id}`;
            let id = baseId;
            if (id === 'highlights' || categoryMap.has(id)) id = `menu-${menu.id}`;
            const firstInCategory = allMenuItems.value.find((item) => item.menu_id === menu.id);
            categoryMap.set(id, {
                id,
                label: menu.title || menu.name || `Menu ${menu.id}`,
                apiId: menu.id,
                image: categoryImage(menu, firstInCategory ?? null),
                count: allMenuItems.value.filter((item) => item.menu_id === menu.id).length || undefined,
            });
        });
    return Array.from(categoryMap.values());
});

const deliveryPrice = computed(() =>
    feesData.value?.delivery_price ? parseFloat(feesData.value.delivery_price).toFixed(2) : '3.00',
);
const minOrderValue = computed(() =>
    feesData.value?.min_order ? parseFloat(String(feesData.value.min_order)).toFixed(2) : '10.00',
);

function handleCategoryClick(categoryId: string) {
    isScrollingProgrammatically.value = true;
    activeCategory.value = categoryId;
    nextTick(() => {
        const section = sectionRefs.value[categoryId];
        if (section) {
            const headerOffset = headerHeight.value + stickySearchHeight.value + 20;
            const elementPosition = section.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
            window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
            setTimeout(() => {
                isScrollingProgrammatically.value = false;
                requestAnimationFrame(() => {
                    activeCategory.value = categoryId;
                });
            }, 1200);
        } else {
            isScrollingProgrammatically.value = false;
        }
    });
}

function addToBasket(item: unknown) {
    basket.addItem(item as StorefrontBasketItem);
}

function scrollToProduct(productId: number) {
    const productElement = document.querySelector(`[data-product-id="${productId}"]`) as HTMLElement | null;
    if (productElement) {
        const headerOffset = headerHeight.value + stickySearchHeight.value + 20;
        const elementPosition = productElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
        window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
        productElement.style.transition = 'box-shadow 0.3s ease';
        productElement.style.boxShadow = '0 0 0 3px rgba(249, 115, 22, 0.5)';
        setTimeout(() => {
            productElement.style.boxShadow = '';
        }, 2000);
        return;
    }
    const product = allMenuItems.value.find((item) => item.id === productId);
    if (!product) return;
    const productCategory = menuCategories.value.find((cat) => cat.id === product.menu_id);
    if (!productCategory) return;
    const category = categories.value.find((cat) => cat.apiId === productCategory.id);
    if (category) {
        handleCategoryClick(category.id);
        setTimeout(() => {
            const productEl = document.querySelector(`[data-product-id="${productId}"]`) as HTMLElement | null;
            if (productEl) {
                productEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                productEl.style.transition = 'box-shadow 0.3s ease';
                productEl.style.boxShadow = '0 0 0 3px rgba(249, 115, 22, 0.5)';
                setTimeout(() => {
                    productEl.style.boxShadow = '';
                }, 2000);
            }
        }, 500);
    }
}

function updateItemQuantity(index: number, newQty: number) {
    basket.updateQuantity(index, newQty);
}

function removeItem(index: number) {
    basket.removeItem(index);
}

function formatItemPrice(item: MenuItem) {
    if (item.price === null || item.price === undefined) return '€ 0.00';
    if (typeof item.price === 'number') return `€ ${item.price.toFixed(2)}`;
    const priceStr = String(item.price);
    return priceStr.startsWith('€') ? priceStr.replace('€', '€ ') : `€ ${priceStr}`;
}

</script>

<template>
    <StorefrontSeo path="/" />
    <JsonLd :data="allHomeJsonLd()" />

    <div class="min-h-screen bg-background transition-colors duration-300">
        <div ref="headerRef" class="sticky top-0 z-50">
            <Header @menu-click="isAccountModalOpen = true" />
        </div>

        <div class="flex w-full">
            <div class="flex-1 min-w-0 lg:mr-[360px]">
                <div ref="heroRef">
                    <HeroSection />
                </div>

                <DiscountBanner />
                <DownloadAppSection />

                <div class="px-4 md:px-6 lg:px-16 xl:px-24">
                    <div ref="restaurantInfoRef" class="py-4 border-b border-border">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-start justify-between">
                                <div class="flex flex-col gap-2 flex-1">
                                    <h1 class="text-xl md:text-2xl font-bold text-foreground">Oscar&apos;s Pizza &amp; Kebab</h1>
                                    <p class="text-sm text-orange-500 font-semibold">
                                        Official website · Order direct · John&apos;s Street, Limerick
                                    </p>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="flex items-center gap-1">
                                            <ShoppingBag class="w-3.5 h-3.5 text-foreground" />
                                            <span class="text-foreground text-sm">Min. € {{ minOrderValue }}</span>
                                        </div>
                                        <span class="text-muted-foreground">•</span>
                                        <div class="flex items-center gap-1">
                                            <Bike class="w-3.5 h-3.5 text-foreground" />
                                            <span class="text-foreground text-sm">€ {{ deliveryPrice }}</span>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="text-foreground hover:text-orange-500 transition p-1"
                                    @click="isInfoModalOpen = true"
                                >
                                    <Info class="w-5 h-5 md:w-6 md:h-6" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    ref="stickySearchRef"
                    class="sticky z-40 bg-background transition-all duration-200"
                    :class="isScrolled ? 'shadow-lg border-b border-border' : ''"
                    :style="{ position: 'sticky', top: headerHeight ? `${headerHeight}px` : '64px', zIndex: 40 }"
                >
                    <div class="px-4 md:px-6 lg:px-16 xl:px-24">
                        <div class="py-3 md:py-4">
                            <div class="relative">
                                <Search
                                    class="absolute left-4 md:left-5 top-1/2 transform -translate-y-1/2 w-5 h-5 md:w-6 md:h-6 text-orange-500"
                                />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search in Oscar's Pizza & Kebab"
                                    class="w-full pl-12 md:pl-14 pr-4 md:pr-5 py-3 md:py-3.5 bg-card text-foreground text-sm md:text-base placeholder-muted-foreground rounded-full border-2 border-border focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition shadow-md"
                                />
                            </div>
                        </div>

                        <div v-if="loading" class="py-2">
                            <div class="text-muted-foreground text-sm">Loading categories...</div>
                        </div>
                        <MenuCategories
                            v-else
                            :categories="categories"
                            :active-category="activeCategory"
                            :set-active-category="handleCategoryClick"
                            :on-list-click="() => { isCategoriesModalOpen = true }"
                        />
                    </div>
                </div>

                <div class="px-4 md:px-6 lg:px-16 xl:px-24">
                    <div v-if="searchQuery.trim().length > 2" class="py-6 md:py-8">
                        <div class="flex items-end justify-between gap-3 mb-4 md:mb-6">
                            <h2 class="text-xl md:text-2xl font-bold text-foreground tracking-tight">
                                Results for “{{ searchQuery }}”
                            </h2>
                            <p v-if="!isSearching" class="text-xs text-muted-foreground tabular-nums">
                                {{ searchResults.length }} items
                            </p>
                        </div>
                        <div v-if="isSearching" class="text-muted-foreground text-sm">Searching…</div>
                        <div
                            v-else-if="searchResults.length > 0"
                            class="grid grid-cols-1 md:grid-cols-2 gap-2.5 md:gap-3"
                        >
                            <button
                                v-for="item in searchResults"
                                :key="item.id"
                                :data-product-id="item.id"
                                type="button"
                                class="group w-full bg-card rounded-2xl p-3 md:p-3.5 flex items-stretch gap-3 text-left hover:bg-muted/50 transition"
                                @click="selectedProduct = item"
                            >
                                <div class="flex-1 min-w-0 flex flex-col justify-center">
                                    <h3 class="text-foreground font-bold text-[15px] md:text-base line-clamp-2">
                                        {{ stripHtml(item.title) }}
                                    </h3>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <p class="text-muted-foreground font-medium text-sm">
                                            {{ formatItemPrice(item) }}
                                        </p>
                                        <span
                                            v-if="item.average_rating && item.average_rating > 0"
                                            class="inline-flex items-center gap-0.5 text-xs font-medium text-orange-500"
                                        >
                                            <Star class="size-3 fill-orange-500 text-orange-500" />
                                            {{ Number(item.average_rating).toFixed(1) }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="item.description"
                                        class="text-muted-foreground/80 text-xs mt-1 line-clamp-2"
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
                                        class="absolute -top-1.5 -right-1.5 w-8 h-8 rounded-full bg-orange-500 text-white flex items-center justify-center shadow-md"
                                        aria-hidden="true"
                                    >+</span>
                                </div>
                            </button>
                        </div>
                        <div v-else class="text-muted-foreground text-sm py-8 text-center">No results found</div>
                    </div>

                    <div v-else-if="apiError" class="py-6">
                        <div class="text-red-400 mb-4">
                            Unable to connect to API. Please check your API configuration.
                        </div>
                        <div v-if="allMenuItems.length > 0" class="text-gray-400 text-sm mb-4">Showing cached menu items:</div>
                    </div>

                    <template v-else>
                        <div
                            id="highlights"
                            :ref="(el) => setSectionRef('highlights', el)"
                        >
                            <div v-if="loading" class="py-4">
                                <h2 class="text-lg md:text-xl font-bold text-foreground mb-3">Highlights</h2>
                                <div class="text-muted-foreground text-sm">Loading highlights...</div>
                            </div>
                            <HighlightsCarousel
                                v-else-if="highlightItems.length > 0"
                                :items="highlightItems"
                                :on-select-product="(p) => { selectedProduct = p as MenuItem }"
                            />
                        </div>

                        <div
                            v-for="category in categories.slice(1)"
                            :id="category.id"
                            :key="`category-${category.id}`"
                            :ref="(el) => setSectionRef(category.id, el)"
                        >
                            <MenuSection
                                :category-id="category.id"
                                :category-label="category.label"
                                :category-api-id="category.apiId"
                                :search-query="searchQuery"
                                :on-add-to-basket="addToBasket"
                                :on-select-product="(p) => { selectedProduct = p as MenuItem }"
                            />
                        </div>
                    </template>
                </div>
            </div>

            <div
                class="hidden lg:block fixed right-0 z-30 w-[360px] overflow-hidden border-l border-border bg-card pointer-events-auto"
                :style="{
                    top: `${Math.max(headerHeight, 56)}px`,
                    height: `calc(100dvh - ${Math.max(headerHeight, 56)}px)`,
                }"
            >
                <BasketSidebar
                    :items="basketItems"
                    :update-quantity="updateItemQuantity"
                    :remove-item="removeItem"
                    :item-click="scrollToProduct"
                />
            </div>
        </div>

        <MobileBasket
            :items="basketItems"
            :update-quantity="updateItemQuantity"
            :remove-item="removeItem"
            :item-click="scrollToProduct"
        />

        <ProductModal
            v-if="selectedProduct"
            :product="selectedProduct"
            @close="selectedProduct = null"
            @add-to-basket="addToBasket"
        />

        <MyAccountModal :is-open="isAccountModalOpen" @close="isAccountModalOpen = false" />

        <CategoriesModal
            :is-open="isCategoriesModalOpen"
            :categories="categories"
            :active-category="activeCategory"
            :on-category-click="handleCategoryClick"
            @close="isCategoriesModalOpen = false"
        />

        <!-- Restaurant Closed Modal -->
        <div
            v-if="isRestaurantClosedModalOpen"
            class="fixed inset-0 bg-black/70 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center z-[60] p-4 animate-in fade-in duration-200"
            @click="dismissClosedModal"
        >
            <div
                class="bg-card rounded-3xl max-w-md w-full flex flex-col overflow-hidden shadow-2xl border border-border animate-in zoom-in-95 duration-200"
                @click.stop
            >
                <div class="flex justify-between items-center p-6 pb-4 flex-shrink-0 border-b border-border">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-orange-500/20 flex items-center justify-center">
                            <Clock class="w-6 h-6 text-orange-500" />
                        </div>
                        <h2 class="text-2xl font-bold text-foreground">We're Currently Closed</h2>
                    </div>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground hover:bg-accent transition p-2 rounded-full"
                        @click="dismissClosedModal"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="px-6 py-6 flex-1">
                    <div class="space-y-4">
                        <p class="text-foreground text-base leading-relaxed">
                            Thank you for visiting Oscar's Pizza & Kebab! We're currently closed and will be opening at
                            <span class="font-bold text-orange-500">3:00 PM</span> today.
                        </p>
                        <p class="text-muted-foreground text-sm leading-relaxed">
                            You can still browse our menu and place an order for later. We'll prepare your order fresh when we open!
                        </p>
                        <div class="bg-muted/50 rounded-xl p-4 border border-border mt-4">
                            <div class="flex items-center gap-2 mb-3">
                                <Clock class="w-4 h-4 text-orange-500" />
                                <span class="text-foreground font-semibold text-sm">Delivery Times</span>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(item, index) in deliveryHours"
                                    :key="index"
                                    class="flex justify-between items-center py-1"
                                >
                                    <span class="text-muted-foreground text-xs font-medium">{{ item.day }}</span>
                                    <span class="text-foreground text-xs font-semibold">{{ item.time }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-border flex gap-3">
                    <button
                        type="button"
                        class="flex-1 px-4 py-3 bg-muted hover:bg-accent text-foreground font-medium rounded-xl transition"
                        @click="dismissClosedModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-xl transition dark:bg-orange-600"
                        @click="dismissClosedModal"
                    >
                        Order for Later
                    </button>
                </div>
            </div>
        </div>

        <!-- Restaurant Info Modal -->
        <div
            v-if="isInfoModalOpen"
            class="fixed inset-0 bg-black/70 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200"
            @click="isInfoModalOpen = false"
        >
            <div
                class="bg-card rounded-3xl max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden shadow-2xl border border-border animate-in zoom-in-95 duration-200"
                @click.stop
            >
                <div
                    class="flex justify-between items-center p-5 md:p-6 pb-4 flex-shrink-0 border-b border-border bg-gradient-to-r from-card to-accent/50"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-orange-500/20 flex items-center justify-center">
                            <Info class="w-5 h-5 text-orange-500" />
                        </div>
                        <h2 class="text-2xl md:text-3xl font-bold text-foreground">About Us</h2>
                    </div>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground hover:bg-accent transition p-2 rounded-full"
                        @click="isInfoModalOpen = false"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="px-5 md:px-6 py-5 overflow-y-auto flex-1 scrollbar-hide">
                    <div class="space-y-6">
                        <div>
                            <div class="bg-card rounded-xl overflow-hidden border border-border shadow-lg">
                                <div class="relative w-full h-64 md:h-72">
                                    <iframe
                                        src="https://maps.google.com/maps?q=Oscar%27s+Pizza+%26+Kebab%2C+17+John%27s+Street%2C+Limerick%2C+Ireland&z=16&output=embed"
                                        title="Oscar's Pizza & Kebab location on Google Maps — 17 John's Street, Limerick"
                                        width="100%"
                                        height="100%"
                                        style="border: 0"
                                        allowfullscreen
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade"
                                        class="w-full h-full"
                                    />
                                    <div
                                        class="absolute top-4 left-4 bg-background/95 backdrop-blur-md rounded-xl p-3 shadow-xl max-w-xs border border-border"
                                    >
                                        <div class="flex items-start gap-2 mb-2">
                                            <MapPin class="w-4 h-4 text-orange-500 mt-0.5" />
                                            <p class="text-foreground text-sm font-semibold">
                                                17 John&apos;s Street, Irishtown, Limerick, V94 KR22
                                            </p>
                                        </div>
                                        <a
                                            href="https://www.google.com/maps/search/?api=1&query=Oscar%27s+Pizza+%26+Kebab%2C+17+John%27s+Street%2C+Limerick%2C+Ireland"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition shadow-md w-full dark:bg-orange-600 inline-block text-center"
                                        >
                                            Open in Maps
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-card rounded-xl p-5 border border-border">
                            <h3 class="text-lg font-bold text-foreground mb-3 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                    <HomeIcon class="w-4 h-4 text-orange-500" />
                                </div>
                                A Little Bit About Us
                            </h3>
                            <p class="text-muted-foreground leading-relaxed text-sm">
                                Indulge your taste buds in a flavorful journey at Oscar&apos;s Pizza &amp; Kebab on John&apos;s Street in Limerick — your local destination for mouthwatering kebabs and delectable pizza. Our chefs skillfully craft each dish, combining traditional recipes with a modern twist, ensuring every bite is a burst of authentic flavors. Whether you&apos;re craving a succulent shawarma, tender grilled meats, or vegetarian delights, Oscar&apos;s has something to satisfy every palate. Experience the essence of Middle Eastern and Mediterranean cuisine, available for collection from Irishtown or delivery across Limerick. Elevate your mealtime today!
                            </p>
                        </div>

                        <div>
                            <h3 class="text-lg font-bold text-foreground mb-3 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                    <Clock class="w-4 h-4 text-orange-500" />
                                </div>
                                Delivery Times
                            </h3>
                            <div class="bg-card rounded-xl p-4 border border-border">
                                <div class="space-y-2.5">
                                    <div
                                        v-for="(item, index) in deliveryHours"
                                        :key="`info-${index}`"
                                        class="flex justify-between items-center py-2 border-b border-border last:border-0"
                                    >
                                        <span class="text-muted-foreground text-sm font-medium">{{ item.day }}</span>
                                        <span class="text-foreground text-sm font-semibold">{{ item.time }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-bold text-foreground mb-3 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                    <Receipt class="w-4 h-4 text-orange-500" />
                                </div>
                                Delivery Information
                            </h3>
                            <div class="bg-card rounded-xl p-4 border border-border">
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b border-border">
                                        <span class="text-muted-foreground text-sm">Minimum order amount</span>
                                        <span class="text-orange-500 text-base font-bold">€ {{ minOrderValue }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-muted-foreground text-sm">Delivery fee</span>
                                        <span class="text-orange-500 text-base font-bold">€ {{ deliveryPrice }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-bold text-foreground mb-3 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                    <Building2 class="w-4 h-4 text-orange-500" />
                                </div>
                                Business Details
                            </h3>
                            <div class="bg-card rounded-xl p-4 border border-border">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-start gap-2">
                                            <Building2 class="w-4 h-4 text-orange-500 mt-0.5 flex-shrink-0" />
                                            <div class="flex-1">
                                                <span class="text-muted-foreground text-sm block mb-1">Business Name</span>
                                                <span class="text-foreground text-sm font-semibold">Oscar's Pizza & Kebab</span>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2 pt-2 border-t border-border">
                                            <MapPin class="w-4 h-4 text-orange-500 mt-0.5 flex-shrink-0" />
                                            <div class="flex-1">
                                                <span class="text-muted-foreground text-sm block mb-1">Address</span>
                                                <span class="text-foreground text-sm font-semibold">
                                                    17 John&apos;s Street<br />Irishtown, Limerick<br />V94 KR22, Ireland
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2 pt-2 border-t border-border">
                                            <Phone class="w-4 h-4 text-orange-500 mt-0.5 flex-shrink-0" />
                                            <div class="flex-1">
                                                <span class="text-muted-foreground text-sm block mb-1">Phone</span>
                                                <a href="tel:+35361597178" class="text-foreground text-sm font-semibold hover:text-orange-500 transition">061 597 178</a>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2 pt-2 border-t border-border">
                                            <Mail class="w-4 h-4 text-orange-500 mt-0.5 flex-shrink-0" />
                                            <div class="flex-1">
                                                <span class="text-muted-foreground text-sm block mb-1">Email / Support</span>
                                                <a
                                                    href="mailto:info@oscarlimerick.com"
                                                    class="text-foreground text-sm font-semibold hover:text-orange-500 transition break-all"
                                                >info@oscarlimerick.com</a>
                                                <a href="/support" class="text-orange-500 text-sm font-semibold hover:underline block mt-1">Visit Support page</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
