<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const props = withDefaults(
    defineProps<{
        tone?: 'on-dark' | 'on-light';
    }>(),
    {
        tone: 'on-dark',
    },
);

const page = usePage();
const name = computed(() => page.props.name);
const logo = computed(() => page.props.logo);
const onDark = computed(() => props.tone === 'on-dark');
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-[var(--radius-control)]"
        :class="
            onDark
                ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                : 'bg-primary text-primary-foreground'
        "
    >
        <img
            v-if="logo"
            :src="logo"
            :alt="name"
            class="size-full object-contain p-0.5"
        />
        <AppLogoIcon
            v-else
            class="size-5 fill-current"
            :class="onDark ? 'text-white dark:text-black' : 'text-primary-foreground'"
        />
    </div>
    <div class="ms-1 grid min-w-0 flex-1 text-start text-sm">
        <span
            class="mb-0.5 truncate leading-tight font-semibold"
            :class="onDark ? 'text-white' : 'text-foreground'"
            >{{ name }}</span
        >
    </div>
</template>
