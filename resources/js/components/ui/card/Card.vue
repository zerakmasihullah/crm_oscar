<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground flex flex-col gap-6 rounded-[var(--radius-card)] border border-[var(--card-border)] py-5 shadow-[var(--shadow-soft)] ui-surface-hover',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
