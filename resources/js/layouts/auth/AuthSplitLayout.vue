<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const appName = computed(() => page.props.name || 'Oscar');
const logo = computed(() => {
    const value = page.props.logo;

    return typeof value === 'string' && value !== ''
        ? value
        : '/storefront/logo.png';
});
</script>

<template>
    <div class="auth-shell">
        <aside class="auth-shell-aside" aria-hidden="true">
            <img
                class="auth-shell-hero"
                src="/images/auth/hero.jpg"
                alt=""
                loading="eager"
                decoding="async"
            />
            <div class="auth-shell-veil" />

            <div class="auth-shell-aside-inner">
                <Link :href="home()" class="auth-shell-logo">
                    <span class="auth-shell-mark">
                        <img
                            :src="logo"
                            :alt="appName"
                            class="size-full object-contain"
                        />
                    </span>
                    {{ appName }}
                </Link>

                <div class="auth-shell-copy">
                    <p class="auth-shell-brand">{{ appName }}</p>
                    <h2>Orders in. Kitchen ready.</h2>
                    <p class="auth-shell-lead">
                        Sign in to run takeaway service — tickets, menu,
                        delivery, and staff in one place.
                    </p>
                </div>
            </div>
        </aside>

        <main class="auth-shell-main">
            <div class="auth-shell-toolbar">
                <Link
                    :href="home()"
                    class="auth-shell-logo auth-shell-logo-mobile"
                >
                    <span class="auth-shell-mark">
                        <img
                            :src="logo"
                            :alt="appName"
                            class="size-full object-contain"
                        />
                    </span>
                    {{ appName }}
                </Link>
                <LanguageSwitcher />
            </div>

            <div class="auth-shell-panel">
                <header v-if="title || description" class="auth-shell-head">
                    <h1 v-if="title">{{ title }}</h1>
                    <p v-if="description">{{ description }}</p>
                </header>
                <div class="auth-shell-form">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
