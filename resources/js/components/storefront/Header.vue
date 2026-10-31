<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { onMounted } from 'vue';
import ThemeToggle from '@/components/storefront/ThemeToggle.vue';

const emit = defineEmits<{ menuClick: [] }>();

onMounted(() => {
    const w = window as Window & { gtranslateSettings?: Record<string, unknown> };
    w.gtranslateSettings = {
        default_language: 'en',
        languages: ['en', 'ga'],
        globe_color: '#ffffff',
        wrapper_selector: '.gtranslate_wrapper',
        globe_size: 16,
    };
    if (!document.querySelector('script[data-gtranslate]')) {
        const script = document.createElement('script');
        script.src = 'https://cdn.gtranslate.net/widgets/latest/globe.js';
        script.defer = true;
        script.dataset.gtranslate = 'true';
        document.body.appendChild(script);
    }
});
</script>

<template>
    <header class="sticky top-0 z-50 bg-background border-b border-border/60">
        <div class="flex items-center justify-between gap-3 h-14 px-3 md:px-5">
            <Link
                href="/"
                class="flex items-center gap-2.5 min-w-0 transition hover:opacity-90"
                aria-label="Oscar's Pizza & Kebab home"
            >
                <img
                    src="/storefront/logo.png"
                    alt="Oscar's Pizza & Kebab"
                    class="size-8 md:size-9 object-contain shrink-0"
                />
                <span class="hidden sm:block text-[15px] font-bold text-foreground tracking-tight truncate">
                    Oscar&apos;s Pizza
                </span>
            </Link>

            <div class="flex items-center gap-1 shrink-0">
                <div
                    class="gtranslate_wrapper flex items-center justify-center size-9 rounded-lg hover:bg-muted transition [&_img]:dark:brightness-0 [&_img]:dark:invert-0 [&_img]:brightness-0 [&_img]:invert"
                />
                <ThemeToggle variant="icon" />
                <button
                    type="button"
                    class="inline-flex items-center justify-center size-9 rounded-lg text-foreground hover:bg-muted hover:text-orange-500 transition"
                    aria-label="Account menu"
                    @click="emit('menuClick')"
                >
                    <Menu class="w-5 h-5" :stroke-width="2" />
                </button>
            </div>
        </div>
    </header>
</template>
