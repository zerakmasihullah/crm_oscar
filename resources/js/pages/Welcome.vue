<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard, login } from '@/routes';
import './welcome/welcome-premium.css';

const page = usePage();
const { t } = useTranslations();

const appName = computed(() => page.props.name || 'Oscar');
const isAuthenticated = computed(() => !!page.props.auth.user);
const logo = computed(() => {
    const value = page.props.logo;

    return typeof value === 'string' && value !== ''
        ? value
        : '/storefront/logo.png';
});

function lt(key: string, fallback: string): string {
    const value = t(key);

    return value === key ? fallback : value;
}
</script>

<template>
    <Head :title="appName" />

    <div class="lp">
        <div class="lp-hero" aria-hidden="true">
            <img
                class="lp-hero-img"
                src="/brand/hero-pizza.jpg"
                alt=""
                width="1920"
                height="1080"
            />
            <span class="lp-hero-scrim" />
            <span class="lp-hero-glow" />
        </div>

        <header class="lp-bar">
            <span class="lp-mark">
                <img :src="logo" :alt="appName" />
            </span>
            <LanguageSwitcher />
        </header>

        <main class="lp-main">
            <p class="lp-brand">{{ appName }}</p>
            <h1>
                {{
                    lt(
                        'landing.hero.line1',
                        'Orders in. Kitchen ready. Served.',
                    )
                }}
            </h1>
            <p class="lp-lead">
                {{
                    lt(
                        'landing.hero.lead',
                        'Run your takeaway from one desk — live orders, menu, delivery, and staff.',
                    )
                }}
            </p>

            <div class="lp-actions">
                <Link
                    v-if="isAuthenticated"
                    :href="dashboard()"
                    class="lp-btn"
                >
                    {{ t('Open dashboard') }}
                    <ArrowRight :size="16" />
                </Link>
                <template v-else>
                    <Link :href="login()" class="lp-btn">
                        {{ t('Log in') }}
                        <ArrowRight :size="16" />
                    </Link>
                    <Link href="/register" class="lp-link">
                        {{ lt('landing.nav.start', 'Create account') }}
                    </Link>
                </template>
            </div>
        </main>
    </div>
</template>
