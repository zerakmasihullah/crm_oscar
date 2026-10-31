<script setup lang="ts">
import AuthBrandLogo from '@/components/AuthBrandLogo.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="auth-page relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-6 md:p-10"
    >
        <div class="auth-page-grain pointer-events-none absolute inset-0" aria-hidden="true" />

        <div class="relative z-10 flex w-full max-w-md flex-col gap-6">
            <AuthBrandLogo size="md" />

            <Card class="rounded-xl border-[var(--school-navy)]/8 bg-white/75 shadow-soft backdrop-blur-sm dark:bg-card/80">
                <CardHeader class="px-10 pt-8 pb-0 text-center">
                    <CardTitle class="text-xl">{{ title }}</CardTitle>
                    <CardDescription>
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="px-10 py-8">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
