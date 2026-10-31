<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        href?: NonNullable<InertiaLinkProps['href']>;
        disabled?: boolean;
        variant?: 'default' | 'outline' | 'secondary' | 'ghost';
    }>(),
    {
        variant: 'default',
    },
);

defineEmits<{ click: [] }>();
</script>

<template>
    <Button
        v-if="href"
        :variant="variant"
        :disabled="disabled"
        as-child
    >
        <Link :href="href">
            <Plus class="size-4" />
            <slot />
        </Link>
    </Button>
    <Button
        v-else
        type="button"
        :variant="variant"
        :disabled="disabled"
        @click="$emit('click')"
    >
        <Plus class="size-4" />
        <slot />
    </Button>
</template>
