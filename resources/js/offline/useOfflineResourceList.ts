import { useOnline } from '@vueuse/core';
import { computed, onMounted, ref  } from 'vue';
import type {Ref} from 'vue';
import {
    listOfflineResource
    
} from '@/offline/repository';
import type {OfflineResourceItem} from '@/offline/repository';

type OnlineItem = {
    id: number;
    client_uuid?: string;
    title?: string;
    name?: string;
    [key: string]: unknown;
};

export type OfflineListDisplayItem<T extends OnlineItem = OnlineItem> = T & {
    client_uuid: string;
    pending_local: boolean;
    offline_only: boolean;
};

/**
 * Merge server pagination with the local IndexedDB cache so any resource
 * Index page can research data while offline without custom wiring.
 */
export function useOfflineResourceList<T extends OnlineItem>(options: {
    resource: string;
    offlineEnabled: boolean | Ref<boolean>;
    onlineItems: Ref<T[]> | (() => T[]);
}) {
    const online = useOnline();
    const offlineItems = ref<OfflineResourceItem[]>([]);
    const loadingCache = ref(false);

    const enabled = computed(() =>
        typeof options.offlineEnabled === 'boolean'
            ? options.offlineEnabled
            : Boolean(options.offlineEnabled.value),
    );

    const serverItems = computed(() =>
        typeof options.onlineItems === 'function'
            ? options.onlineItems()
            : options.onlineItems.value,
    );

    const displayItems = computed<OfflineListDisplayItem[]>(() => {
        if (online.value || ! enabled.value) {
            return serverItems.value.map((item) => ({
                ...item,
                client_uuid: String(item.client_uuid ?? item.id),
                pending_local: false,
                offline_only: false,
            }));
        }

        return offlineItems.value.map((item) => ({
            ...item,
            id: Number(item.id ?? 0),
            client_uuid: item.client_uuid,
            title: item.title ?? item.name,
            name: item.name ?? item.title,
            pending_local: Boolean(item.pending_local),
            offline_only: ! item.id,
        })) as OfflineListDisplayItem[];
    });

    async function refreshCache(): Promise<void> {
        if (! enabled.value) {
            offlineItems.value = [];

            return;
        }

        loadingCache.value = true;

        try {
            offlineItems.value = await listOfflineResource(options.resource);
        } catch {
            offlineItems.value = [];
        } finally {
            loadingCache.value = false;
        }
    }

    onMounted(() => {
        void refreshCache();
    });

    return {
        online,
        enabled,
        displayItems,
        offlineItems,
        loadingCache,
        refreshCache,
        showingOfflineCache: computed(() => enabled.value && ! online.value),
    };
}
