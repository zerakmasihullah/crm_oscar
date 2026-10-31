<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ChefHat, Home } from '@lucide/vue';
import { computed } from 'vue';
import Header from '@/components/storefront/Header.vue';
import StorefrontSeo from '@/components/storefront/StorefrontSeo.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{ status: number }>();

const title = computed(() => {
    if (props.status === 403) return 'Access denied';
    if (props.status === 404) return 'Page not found';
    if (props.status === 503) return 'We’ll be right back';
    return 'Something went wrong';
});

const message = computed(() => {
    if (props.status === 403) return 'You don’t have permission to view this page.';
    if (props.status === 404) return 'The page you’re looking for doesn’t exist or may have moved.';
    if (props.status === 503) return 'We’re doing a short update. Please try again in a moment.';
    return 'An unexpected error occurred. Please try again.';
});

function goBack() {
    window.history.back();
}
</script>

<template>
    <StorefrontSeo :title="`${title} (${status})`" path="/" :no-index="true" />

    <div class="relative min-h-screen bg-background">
        <Header />

        <div class="mx-auto flex max-w-2xl flex-col items-center px-4 py-16 text-center sm:py-24">
            <div class="relative mb-8">
                <div
                    class="flex size-28 items-center justify-center rounded-full bg-orange-500/10 sm:size-36 dark:bg-orange-500/20"
                >
                    <ChefHat class="size-14 text-orange-500 sm:size-20" />
                </div>
                <div
                    class="absolute -top-1 -right-1 flex size-12 items-center justify-center rounded-full bg-orange-500 text-lg font-bold text-white shadow-lg sm:size-14 sm:text-xl"
                >
                    {{ status }}
                </div>
            </div>

            <h1 class="mb-3 text-3xl font-bold tracking-tight text-foreground sm:text-5xl">
                {{ title }}
            </h1>
            <p class="mb-8 max-w-md text-sm text-muted-foreground sm:text-base">
                {{ message }}
            </p>

            <div class="flex w-full flex-col items-stretch justify-center gap-3 sm:w-auto sm:flex-row">
                <Link
                    href="/"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-orange-500 px-8 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600"
                >
                    <Home class="size-4" />
                    Go to home
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-border bg-card px-8 py-3 text-sm font-semibold text-foreground transition hover:bg-muted"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" />
                    Go back
                </button>
            </div>

            <p class="mt-12 border-t border-border pt-6 text-sm text-muted-foreground">
                Need help?
                <Link href="/" class="text-orange-500 underline hover:text-orange-600">Browse the menu</Link>
                or
                <Link href="/my-orders" class="text-orange-500 underline hover:text-orange-600">check your orders</Link>
            </p>
        </div>

        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="absolute top-1/4 left-1/4 size-64 rounded-full bg-orange-500/5 blur-3xl dark:bg-orange-500/10" />
            <div class="absolute right-1/4 bottom-1/4 size-64 rounded-full bg-orange-500/5 blur-3xl dark:bg-orange-500/10" />
        </div>
    </div>
</template>
