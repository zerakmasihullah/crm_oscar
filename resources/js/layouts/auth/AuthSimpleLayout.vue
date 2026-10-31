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
        : '/brand/oscar-mark.svg';
});
</script>

<template>
    <div class="auth-stage">
        <div class="auth-stage-glow" aria-hidden="true" />

        <header class="auth-stage-top">
            <Link :href="home()" class="auth-stage-logo">
                <span class="auth-stage-mark">
                    <img
                        :src="logo"
                        :alt="appName"
                        class="size-full object-contain"
                    />
                </span>
                {{ appName }}
            </Link>
            <LanguageSwitcher />
        </header>

        <main class="auth-stage-main">
            <section class="auth-stage-card">
                <header v-if="title || description" class="auth-stage-head">
                    <h1 v-if="title">{{ title }}</h1>
                    <p v-if="description">{{ description }}</p>
                </header>
                <div class="auth-stage-body">
                    <slot />
                </div>
            </section>
        </main>
    </div>
</template>
