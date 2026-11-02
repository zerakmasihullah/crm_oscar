<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { home } from '@/routes';

const props = withDefaults(
    defineProps<{
        linked?: boolean;
        showName?: boolean;
        size?: 'md' | 'lg';
        class?: HTMLAttributes['class'];
    }>(),
    {
        linked: true,
        showName: true,
        size: 'lg',
    },
);

const page = usePage();
const name = computed(() => page.props.name || 'Oscar');
const logo = computed(() => {
    const value = page.props.logo;

    return typeof value === 'string' && value !== ''
        ? value
        : '/storefront/logo.png';
});

const markClass = computed(() =>
    props.size === 'lg' ? 'size-16' : 'size-12',
);
</script>

<template>
    <component
        :is="linked ? Link : 'div'"
        v-bind="linked ? { href: home() } : {}"
        :class="[
            'group flex flex-col items-center gap-3 text-center',
            props.class,
        ]"
    >
        <div
            :class="[
                'flex items-center justify-center overflow-hidden',
                markClass,
            ]"
        >
            <img
                :src="logo"
                :alt="name"
                class="max-h-full max-w-full object-contain"
            />
        </div>
        <span
            v-if="showName"
            class="font-display text-2xl font-semibold tracking-tight text-[var(--school-navy,#14231f)] dark:text-white"
        >
            {{ name }}
        </span>
        <span v-else class="sr-only">{{ name }}</span>
    </component>
</template>
