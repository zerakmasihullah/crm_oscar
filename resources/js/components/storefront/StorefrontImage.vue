<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { DEFAULT_IMAGE, resolveImageUrl } from '@/lib/storefront/image-utils';

const props = withDefaults(
    defineProps<{
        src?: string | null;
        alt?: string;
        class?: string;
        imgClass?: string;
        eager?: boolean;
        sizes?: string;
    }>(),
    {
        src: null,
        alt: '',
        class: '',
        imgClass: 'w-full h-full object-cover',
        eager: false,
        sizes: undefined,
    },
);

const failed = ref(false);
const resolved = computed(() => (failed.value ? DEFAULT_IMAGE : resolveImageUrl(props.src)));

watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);

function onError() {
    if (!failed.value) failed.value = true;
}
</script>

<template>
    <img
        :src="resolved"
        :alt="alt"
        :class="[imgClass, props.class]"
        :loading="eager ? 'eager' : 'lazy'"
        :decoding="eager ? 'sync' : 'async'"
        :fetchpriority="eager ? 'high' : 'auto'"
        :sizes="sizes"
        draggable="false"
        @error="onError"
    />
</template>
