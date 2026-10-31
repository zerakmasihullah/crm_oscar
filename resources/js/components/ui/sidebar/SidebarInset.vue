<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <main
    data-slot="sidebar-inset"
    :class="cn(
      'relative flex min-h-svh min-w-0 flex-1 flex-col bg-app-shell',
      props.class,
    )"
  >
    <slot />
  </main>
</template>
