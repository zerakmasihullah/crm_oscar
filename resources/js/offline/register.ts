import { registerSW } from 'virtual:pwa-register';
import { ensureUserScope  } from '@/offline/lifecycle';
import type {OfflineUserIdentity} from '@/offline/lifecycle';
import {
    flushOutbox,
    pullAllResources,
    requestBackgroundSync,
    warmCache,
} from '@/offline/outbox';
import { pruneExpiredCache } from '@/offline/repository';
import { readOfflineSettingsFromPage, registeredOfflineResources } from '@/offline/settings';
import type { OfflineClientSettings } from '@/offline/settings';

type OfflineBootstrap = {
    enabled: boolean;
    pwa_enabled: boolean;
    auto_sync: boolean;
    warm_cache: boolean;
};

let coordinatorStarted = false;
let flushInFlight: Promise<unknown> | null = null;

function readMetaBootstrap(): OfflineBootstrap {
    if (typeof document === 'undefined') {
        return {
            enabled: false,
            pwa_enabled: false,
            auto_sync: false,
            warm_cache: false,
        };
    }

    const meta = document.querySelector('meta[name="kit-offline"]');

    return {
        enabled: meta?.getAttribute('content') === '1',
        pwa_enabled: meta?.getAttribute('data-pwa') === '1',
        auto_sync: meta?.getAttribute('data-auto-sync') === '1',
        warm_cache: meta?.getAttribute('data-warm') === '1',
    };
}

async function unregisterServiceWorkers(): Promise<void> {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    const regs = await navigator.serviceWorker.getRegistrations();

    await Promise.all(regs.map((reg) => reg.unregister()));
}

export async function coordinatedFlush(): Promise<void> {
    if (flushInFlight) {
        await flushInFlight;

        return;
    }

    flushInFlight = flushOutbox()
        .catch(() => undefined)
        .finally(() => {
            flushInFlight = null;
        });

    await flushInFlight;
}

async function refreshCaches(settings: OfflineClientSettings): Promise<void> {
    if (! settings.enabled || ! navigator.onLine) {
        return;
    }

    await pruneExpiredCache().catch(() => undefined);

    if (settings.warm_cache) {
        await warmCache().catch(() => undefined);
    }

    await pullAllResources(registeredOfflineResources()).catch(() => undefined);
}

function startCoordinator(settings: OfflineClientSettings): void {
    if (coordinatorStarted || typeof window === 'undefined') {
        return;
    }

    coordinatorStarted = true;

    const onOnline = () => {
        if (settings.auto_sync) {
            void coordinatedFlush();
            void requestBackgroundSync();
        }

        void refreshCaches(settings);
    };

    const onVisibility = () => {
        if (document.visibilityState === 'visible' && settings.auto_sync) {
            void coordinatedFlush();
        }
    };

    const onMessage = (event: MessageEvent) => {
        if (event.data?.type === 'kit-flush-outbox' && settings.auto_sync) {
            void coordinatedFlush();
        }
    };

    window.addEventListener('online', onOnline);
    document.addEventListener('visibilitychange', onVisibility);
    navigator.serviceWorker?.addEventListener('message', onMessage);

    if (settings.auto_sync && navigator.onLine) {
        void coordinatedFlush();
    }

    void refreshCaches(settings);
}

export function initializeOffline(override?: Partial<OfflineBootstrap>): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const bootstrap = { ...readMetaBootstrap(), ...override };

    if (! bootstrap.enabled) {
        void unregisterServiceWorkers();

        return;
    }

    if (bootstrap.pwa_enabled) {
        registerSW({
            immediate: true,
            onRegisteredSW(_swUrl, registration) {
                if (registration) {
                    void registration.update();
                }
            },
        });
    } else {
        void unregisterServiceWorkers();
    }
}

export async function initializeOfflineForUser(
    user: OfflineUserIdentity | null,
    pageProps?: {
        offlineEnabled?: boolean;
        offlineSettings?: Partial<OfflineClientSettings>;
    },
): Promise<void> {
    const settings = readOfflineSettingsFromPage(pageProps ?? {});

    initializeOffline({
        enabled: settings.enabled,
        pwa_enabled: settings.pwa_enabled,
        auto_sync: settings.auto_sync,
        warm_cache: settings.warm_cache,
    });

    if (! settings.enabled || ! user) {
        return;
    }

    const bound = await ensureUserScope(user);

    if (! bound) {
        return;
    }

    startCoordinator(settings);
}
