<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

const props = defineProps<{
    success?: string | null;
    error?: string | null;
    class?: string;
}>();

const page = usePage();

const success = computed(
    () =>
        props.success ??
        (page.props.flash as { success?: string } | undefined)?.success ??
        null,
);

const error = computed(
    () =>
        props.error ??
        (page.props.flash as { error?: string } | undefined)?.error ??
        null,
);

const message = computed(() => error.value || success.value || null);
</script>

<template>
    <div
        v-if="message"
        :class="
            cn(
                'rounded-xl border px-4 py-3 text-sm',
                error
                    ? 'border-destructive/40 bg-destructive/5 text-destructive'
                    : 'border-border/70 bg-muted/40 text-foreground',
                props.class,
            )
        "
    >
        {{ message }}
    </div>
</template>
