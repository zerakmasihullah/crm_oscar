<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import SiteFooter from '@/components/storefront/SiteFooter.vue';
import { useStorefrontAuth } from '@/composables/storefront/useStorefrontAuth';

const auth = useStorefrontAuth();
const navigating = ref(false);
let removeStart: (() => void) | undefined;
let removeFinish: (() => void) | undefined;

onMounted(async () => {
    if (typeof window === 'undefined') {
        return;
    }
    const stored = localStorage.getItem('storefront-theme');
    let dark = true;
    if (stored === 'light') dark = false;
    else if (stored === 'dark') dark = true;
    else if (stored === 'system' || !stored) {
        dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    document.documentElement.classList.toggle('dark', dark);

    removeStart = router.on('start', () => {
        navigating.value = true;
    });
    removeFinish = router.on('finish', () => {
        navigating.value = false;
    });

    const params = new URLSearchParams(window.location.search);
    const token = params.get('storefront_token');
    const authError = params.get('auth_error');

    if (token) {
        try {
            await auth.loginWithToken(token);
        } catch {
            // ignore — user can retry from account menu
        }
        params.delete('storefront_token');
        const next = `${window.location.pathname}${params.toString() ? `?${params}` : ''}${window.location.hash}`;
        window.history.replaceState({}, '', next);
    } else if (authError) {
        try {
            sessionStorage.setItem('storefront_auth_error', authError);
        } catch {
            // ignore
        }
        params.delete('auth_error');
        const next = `${window.location.pathname}${params.toString() ? `?${params}` : ''}${window.location.hash}`;
        window.history.replaceState({}, '', next);
        window.dispatchEvent(new CustomEvent('storefront:auth-error', { detail: authError }));
    }
});

onUnmounted(() => {
    removeStart?.();
    removeFinish?.();
});
</script>

<template>
    <div class="storefront-shell relative flex min-h-screen flex-col font-sans antialiased">
        <div
            v-show="navigating"
            class="pointer-events-none fixed inset-x-0 top-0 z-[100] h-0.5 overflow-hidden"
            aria-hidden="true"
        >
            <div class="h-full w-full origin-left animate-pulse bg-orange-500" />
        </div>
        <div class="flex flex-1 flex-col">
            <slot />
        </div>
        <SiteFooter />
    </div>
</template>
