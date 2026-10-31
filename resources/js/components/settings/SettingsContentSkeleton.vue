<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(
    defineProps<{
        /** denser list rows for Users / Roles / Backups style pages */
        variant?: 'form' | 'list';
        rows?: number;
    }>(),
    {
        variant: 'list',
        rows: 6,
    },
);
</script>

<template>
    <div
        class="space-y-6"
        data-tour="settings-content-skeleton"
        aria-busy="true"
        aria-live="polite"
    >
        <div
            class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-soft"
        >
            <div
                class="flex items-start justify-between gap-4 border-b border-border/60 bg-muted/20 px-5 py-4"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <Skeleton class="h-5 w-40 max-w-[50%]" />
                    <Skeleton class="h-3.5 w-64 max-w-[80%]" />
                </div>
                <Skeleton class="hidden h-9 w-28 shrink-0 sm:block" />
            </div>

            <div class="space-y-4 p-5">
                <template v-if="variant === 'form'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Skeleton class="h-3 w-20" />
                            <Skeleton class="h-10 w-full" />
                        </div>
                        <div class="space-y-2">
                            <Skeleton class="h-3 w-24" />
                            <Skeleton class="h-10 w-full" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Skeleton class="h-3 w-28" />
                        <Skeleton class="h-24 w-full" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Skeleton class="h-9 w-24" />
                        <Skeleton class="h-9 w-28" />
                    </div>
                </template>

                <template v-else>
                    <div class="flex flex-wrap items-center gap-3">
                        <Skeleton class="h-10 w-full max-w-sm" />
                        <Skeleton class="h-10 w-28" />
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="n in rows"
                            :key="n"
                            class="rounded-xl border border-border/70 bg-background/60 p-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0 flex-1 space-y-2">
                                    <Skeleton class="h-4 w-44 max-w-[70%]" />
                                    <Skeleton class="h-3 w-56 max-w-[90%]" />
                                    <div class="flex gap-2 pt-1">
                                        <Skeleton class="h-5 w-16 rounded-full" />
                                        <Skeleton class="h-5 w-14 rounded-full" />
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 items-center gap-2 self-end sm:self-center"
                                >
                                    <Skeleton class="h-8 w-8 rounded-lg" />
                                    <Skeleton class="h-8 w-20 rounded-lg" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <Skeleton class="h-3 w-28" />
                        <div class="flex gap-2">
                            <Skeleton class="h-8 w-8" />
                            <Skeleton class="h-8 w-8" />
                            <Skeleton class="h-8 w-8" />
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
