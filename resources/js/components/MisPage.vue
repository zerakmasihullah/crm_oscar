<script setup lang="ts">
defineProps<{
    title?: string;
    narrow?: boolean;
}>();
</script>

<template>
    <div
        class="flex w-full flex-1 flex-col gap-6"
        :class="narrow ? 'max-w-5xl' : ''"
    >
        <slot />
    </div>
</template>
