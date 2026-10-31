<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const { t } = useTranslations();
const { can } = usePermissions();
const { isCurrentOrParentUrl } = useCurrentUrl();

const visible = computed(() => can('orders.create'));
const active = computed(() => isCurrentOrParentUrl('/orders/create'));
</script>

<template>
    <Link
        v-if="visible"
        href="/orders/create"
        class="orvion-glass-chip group inline-flex h-10 items-center gap-2 rounded-full px-3 text-sm text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white"
        :class="
            active
                ? 'bg-zinc-900 text-white hover:text-white dark:bg-white dark:text-zinc-900 dark:hover:text-zinc-900'
                : undefined
        "
        :aria-label="t('New Order')"
        :title="t('New Order')"
    >
        <Plus class="size-4 shrink-0 opacity-70" stroke-width="1.75" />
        <span class="hidden max-w-[9rem] truncate sm:inline">{{
            t('New Order')
        }}</span>
    </Link>
</template>
