<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { applyDocumentLocale } from '@/composables/useLocale';
import type { LocaleCode, TextDirection } from '@/types/locale';

const page = usePage();

watch(
    () => [page.props.locale, page.props.dir] as const,
    ([locale, dir]) => {
        if (typeof locale === 'string' && typeof dir === 'string') {
            applyDocumentLocale(locale as LocaleCode, dir as TextDirection);
        }
    },
    { immediate: true },
);
</script>

<template>
    <slot />
</template>
