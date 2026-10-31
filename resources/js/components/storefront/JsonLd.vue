<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    data: Record<string, unknown> | Record<string, unknown>[];
}>();

const json = computed(() => {
    const payload = Array.isArray(props.data) ? props.data : [props.data];
    return JSON.stringify(payload.length === 1 ? payload[0] : payload);
});
</script>

<template>
    <component :is="'script'" type="application/ld+json" v-text="json" />
</template>
