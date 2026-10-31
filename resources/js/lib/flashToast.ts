import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    let lastSignature: string | null = null;
    let lastAt = 0;

    function showOnce(
        type: 'success' | 'error',
        message: string,
        extra?: string,
    ): void {
        const signature = `${type}:${message}:${extra ?? ''}`;
        const now = Date.now();

        if (signature === lastSignature && now - lastAt < 1500) {
            return;
        }

        lastSignature = signature;
        lastAt = now;
        toast[type](message);
    }

    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (data) {
            showOnce(data.type, data.message, 'toast');
        }

        if (typeof flash?.success === 'string' && flash.success) {
            showOnce('success', flash.success);
        }

        if (typeof flash?.error === 'string' && flash.error) {
            showOnce('error', flash.error);
        }
    });

    router.on('success', (event) => {
        const page = (event as CustomEvent).detail?.page;
        const flash = page?.props?.flash as
            | { success?: string | null; error?: string | null }
            | undefined;

        if (flash?.success) {
            showOnce('success', flash.success);
        }

        if (flash?.error) {
            showOnce('error', flash.error);
        }
    });
}
