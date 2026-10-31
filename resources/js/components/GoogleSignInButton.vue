<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { redirect as googleRedirect } from '@/routes/auth/google';

withDefaults(
    defineProps<{
        label?: string;
        showSeparator?: boolean;
        separator?: string;
    }>(),
    {
        label: 'Continue with Google',
        showSeparator: true,
        separator: 'Or continue with email',
    },
);
</script>

<template>
    <div>
        <Button as-child variant="outline" class="h-11 w-full" data-test="google-login-button">
            <a :href="googleRedirect.url()">
                <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        fill="#EA4335"
                        d="M12 10.2v3.6h5.1c-.2 1.2-.9 2.3-1.9 3l3.1 2.4c1.8-1.7 2.9-4.1 2.9-7 0-.7-.1-1.3-.2-1.9H12z"
                    />
                    <path
                        fill="#34A853"
                        d="M6.6 14.3 5.8 14.9l-2.7 2.1C4.8 19.7 8.1 22 12 22c2.7 0 5-.9 6.6-2.4l-3.1-2.4c-.9.6-2 1-3.5 1-2.7 0-5-1.8-5.8-4.3z"
                    />
                    <path
                        fill="#4A90E2"
                        d="M3.1 7.1C2.4 8.5 2 10.2 2 12s.4 3.5 1.1 4.9l3.5-2.7C6.2 13.5 6 12.8 6 12s.2-1.5.5-2.2L3.1 7.1z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M12 6c1.5 0 2.8.5 3.8 1.5l2.8-2.8C16.9 3.1 14.7 2 12 2 8.1 2 4.8 4.3 3.1 7.1l3.4 2.7C7 7.8 9.3 6 12 6z"
                    />
                </svg>
                {{ label }}
            </a>
        </Button>

        <div v-if="showSeparator" class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <Separator class="w-full" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">
                    {{ separator }}
                </span>
            </div>
        </div>
    </div>
</template>
