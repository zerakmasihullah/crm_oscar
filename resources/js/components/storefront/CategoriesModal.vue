<script setup lang="ts">
import { Check, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import StorefrontImage from '@/components/storefront/StorefrontImage.vue';

interface Category {
    id: string;
    label: string;
    image?: string;
    count?: number;
}

const props = defineProps<{
    isOpen: boolean;
    categories: Category[];
    activeCategory: string;
    onCategoryClick: (id: string) => void;
}>();

const emit = defineEmits<{ close: [] }>();

const DEFAULT_IMAGE = '/brand/menu-default.jpg';
const query = ref('');

watch(
    () => props.isOpen,
    (open) => {
        if (open) query.value = '';
    },
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.categories;
    return props.categories.filter((c) => c.label.toLowerCase().includes(q));
});

function chipImage(category: Category) {
    return category.image || DEFAULT_IMAGE;
}

function handleCategoryClick(categoryId: string) {
    props.onCategoryClick(categoryId);
    emit('close');
}
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-end md:items-center justify-center bg-black/65 dark:bg-black/75 backdrop-blur-sm p-0 md:p-4"
        @click="emit('close')"
    >
        <div
            class="bg-card w-full md:max-w-lg max-h-[88vh] md:max-h-[80vh] flex flex-col overflow-hidden shadow-2xl border border-border rounded-t-3xl md:rounded-3xl"
            @click.stop
        >
            <div class="flex justify-center pt-3 md:hidden">
                <div class="w-10 h-1 rounded-full bg-border" />
            </div>

            <div class="flex items-start justify-between gap-3 px-5 md:px-6 pt-3 md:pt-5 pb-3">
                <div>
                    <h2 class="text-xl md:text-2xl font-bold text-foreground tracking-tight">Categories</h2>
                    <p class="text-xs md:text-sm text-muted-foreground mt-0.5">
                        {{ categories.length }} sections · jump to any menu
                    </p>
                </div>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground transition p-2 hover:bg-accent rounded-full"
                    aria-label="Close categories"
                    @click="emit('close')"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="px-5 md:px-6 pb-3">
                <div class="relative">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-orange-500" />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Search categories…"
                        class="w-full h-11 pl-10 pr-4 rounded-2xl bg-muted/60 border border-border text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition"
                    />
                </div>
            </div>

            <div class="overflow-y-auto flex-1 px-4 md:px-5 pb-5">
                <div v-if="filtered.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                    No categories match “{{ query }}”
                </div>
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button
                        v-for="category in filtered"
                        :key="category.id"
                        type="button"
                        class="group flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition active:scale-[0.99]"
                        :class="
                            activeCategory === category.id
                                ? 'border-orange-500/50 bg-orange-500/10 shadow-sm shadow-orange-500/10'
                                : 'border-border bg-card hover:border-orange-500/30 hover:bg-accent/60'
                        "
                        @click="handleCategoryClick(category.id)"
                    >
                        <StorefrontImage
                            :src="chipImage(category)"
                            :alt="category.label"
                            :img-class="
                                activeCategory === category.id
                                    ? 'size-11 shrink-0 rounded-full object-cover ring-2 ring-orange-500/40 shadow-sm'
                                    : 'size-11 shrink-0 rounded-full object-cover ring-2 ring-border shadow-sm'
                            "
                        />
                        <span class="flex-1 min-w-0">
                            <span
                                class="block text-sm font-semibold truncate"
                                :class="
                                    activeCategory === category.id ? 'text-orange-500' : 'text-foreground'
                                "
                            >
                                {{ category.label }}
                            </span>
                            <span v-if="category.count" class="block text-[11px] text-muted-foreground mt-0.5">
                                {{ category.count }} items
                            </span>
                        </span>
                        <Check
                            v-if="activeCategory === category.id"
                            class="w-4 h-4 text-orange-500 shrink-0"
                        />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
