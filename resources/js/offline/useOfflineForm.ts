import { useOnline } from '@vueuse/core';
import { onMounted, onUnmounted, ref, toValue  } from 'vue';
import type {MaybeRefOrGetter} from 'vue';
import {
    enqueueMutation,
    pendingCount,
    requestBackgroundSync,
} from '@/offline/outbox';
import { coordinatedFlush } from '@/offline/register';

type UseOfflineFormOptions = {
    resource: string;
    offlineEnabled: MaybeRefOrGetter<boolean>;
    onlineSubmit: (payload: Record<string, unknown>) => void | Promise<void>;
    action?: 'create' | 'update' | 'upsert';
    initialClientUuid?: string;
};

export function useOfflineForm(options: UseOfflineFormOptions) {
    const online = useOnline();
    const processing = ref(false);
    const queued = ref(false);
    const clientUuid = ref(options.initialClientUuid ?? crypto.randomUUID());
    const error = ref<string | null>(null);

    async function submit(payload: Record<string, unknown>) {
        processing.value = true;
        queued.value = false;
        error.value = null;

        const body = {
            ...payload,
            client_uuid: clientUuid.value,
        };

        try {
            const offlineEnabled = toValue(options.offlineEnabled);

            if (! offlineEnabled || online.value) {
                await options.onlineSubmit(body);

                return;
            }

            await enqueueMutation({
                resource: options.resource,
                action: options.action ?? 'upsert',
                body,
                clientUuid: clientUuid.value,
            });
            queued.value = true;
            await requestBackgroundSync();
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Submit failed';

            throw e;
        } finally {
            processing.value = false;
        }
    }

    return {
        online,
        processing,
        queued,
        clientUuid,
        error,
        submit,
    };
}

export function useOfflineSync(enabled: MaybeRefOrGetter<boolean>, autoSync = true) {
    const online = useOnline();
    const pending = ref(0);
    const lastFlush = ref<string | null>(null);
    let timer: ReturnType<typeof setInterval> | null = null;

    async function refresh() {
        if (! toValue(enabled)) {
            pending.value = 0;

            return;
        }

        try {
            pending.value = await pendingCount();
        } catch {
            pending.value = 0;
        }
    }

    async function flush() {
        if (! toValue(enabled) || ! online.value) {
            return;
        }

        const result = await coordinatedFlush();
        lastFlush.value = new Date().toISOString();
        await refresh();

        return result;
    }

    onMounted(() => {
        if (! toValue(enabled)) {
            return;
        }

        void refresh();

        if (autoSync && online.value) {
            void flush();
        }

        timer = setInterval(() => void refresh(), 5000);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
        }
    });

    return {
        online,
        pending,
        lastFlush,
        refresh,
        flush,
    };
}
