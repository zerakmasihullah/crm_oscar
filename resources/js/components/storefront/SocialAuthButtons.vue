<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        hint?: string;
    }>(),
    {
        hint: 'Or continue with email',
    },
);

const page = usePage();
const googleEnabled = computed(
    () => !!(page.props as { storefrontGoogleAuth?: boolean }).storefrontGoogleAuth,
);

const notice = ref<string | null>(null);

function startGoogle() {
    notice.value = null;
    if (!googleEnabled.value) {
        notice.value = 'Google sign-in is not configured yet.';
        return;
    }
    window.location.href = '/auth/google?intent=storefront';
}
</script>

<template>
    <div class="space-y-4">
        <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-background px-3 py-3 text-sm font-semibold text-foreground transition hover:bg-muted/60"
            @click="startGoogle"
        >
            <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
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
            Continue with Google
        </button>

        <p v-if="notice" class="text-center text-xs text-muted-foreground">{{ notice }}</p>

        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-border" />
            </div>
            <div class="relative flex justify-center">
                <span class="bg-card px-3 text-xs text-muted-foreground">{{ props.hint }}</span>
            </div>
        </div>
    </div>
</template>
