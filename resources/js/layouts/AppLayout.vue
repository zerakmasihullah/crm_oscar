<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AiChatPanel from '@/components/AiChatPanel.vue';
import AiFormFill from '@/components/AiFormFill.vue';
import AiInlineEdit from '@/components/AiInlineEdit.vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { normalizeThemeColors } from '@/lib/theme-colors';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage();

const shell = computed(
    () => normalizeThemeColors(page.props.themeColors).shell,
);

const useHeaderShell = computed(() => shell.value === 'header');
</script>

<template>
    <AppHeaderLayout v-if="useHeaderShell" :breadcrumbs="breadcrumbs">
        <slot />
    </AppHeaderLayout>
    <AppSidebarLayout v-else :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
    <AiInlineEdit />
    <AiFormFill />
    <AiChatPanel />
</template>
