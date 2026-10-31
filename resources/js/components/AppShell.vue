<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LocaleSync from '@/components/LocaleSync.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const sidebarOpen = computed(() => Boolean(usePage().props.sidebarOpen));
</script>

<template>
    <LocaleSync>
        <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
            <slot />
        </div>
        <SidebarProvider v-else :default-open="sidebarOpen">
            <slot />
        </SidebarProvider>
    </LocaleSync>
</template>
