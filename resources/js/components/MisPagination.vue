<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatNumber,
    normalizePagination,
    type PaginatedLike,
} from '@/lib/format';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        /** Laravel/Inertia paginator (`data` + flat keys or nested `meta`). */
        pagination: PaginatedLike | null | undefined;
        /** Optional Inertia `only` keys for partial reloads. */
        only?: string[];
        class?: string;
    }>(),
    {
        only: undefined,
        class: undefined,
    },
);

const { t } = useTranslations();

const normalized = computed(() => normalizePagination(props.pagination));

const visible = computed(() => {
    const page = normalized.value;
    if (!page) {
        return false;
    }

    if (page.simple) {
        return (
            page.current_page > 1 ||
            Boolean(page.has_more) ||
            page.links.some((link) => Boolean(link.url))
        );
    }

    return page.last_page > 1 || (page.links?.length ?? 0) > 3;
});

const summary = computed(() => {
    const page = normalized.value;
    if (!page) {
        return '';
    }

    if (page.simple) {
        return t('Showing :from–:to', {
            from: formatNumber(page.from ?? 0),
            to: formatNumber(page.to ?? 0),
        });
    }

    return t('Showing :from–:to of :total', {
        from: formatNumber(page.from ?? 0),
        to: formatNumber(page.to ?? 0),
        total: formatNumber(page.total),
    });
});

const linkClass = (active: boolean): string =>
    cn(
        'inline-flex min-w-9 items-center justify-center rounded-md px-3 py-1 text-sm transition-colors',
        active
            ? 'bg-primary text-primary-foreground'
            : 'hover:bg-muted text-foreground',
    );
</script>

<template>
    <div
        v-if="visible && normalized"
        :class="
            cn(
                'flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between',
                props.class,
            )
        "
    >
        <p class="text-sm text-muted-foreground">
            {{ summary }}
        </p>
        <nav
            v-if="normalized.links.length > 0"
            class="flex flex-wrap gap-1"
            :aria-label="t('Pagination')"
        >
            <template
                v-for="(link, index) in normalized.links"
                :key="`${index}-${link.label}`"
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="linkClass(link.active)"
                    :only="only"
                    preserve-scroll
                    preserve-state
                    v-html="link.label"
                />
                <span
                    v-else
                    class="inline-flex min-w-9 items-center justify-center px-3 py-1 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
