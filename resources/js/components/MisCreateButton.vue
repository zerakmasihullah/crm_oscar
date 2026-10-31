<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';

const props = withDefaults(
    defineProps<{
        href: NonNullable<InertiaLinkProps['href']>;
        permission: string;
        size?: 'default' | 'sm' | 'lg' | 'icon';
        variant?: 'default' | 'outline' | 'secondary' | 'ghost';
        class?: string;
    }>(),
    {
        size: 'default',
        variant: 'default',
    },
);

const { can } = usePermissions();
</script>

<template>
    <Button
        v-if="can(permission)"
        as-child
        :size="size"
        :variant="variant"
        :class="props.class"
    >
        <Link :href="href">
            <Plus class="size-4" />
            <slot />
        </Link>
    </Button>
</template>
