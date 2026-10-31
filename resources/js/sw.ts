/// <reference lib="webworker" />
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { NetworkOnly } from 'workbox-strategies';

declare let self: ServiceWorkerGlobalScope;

precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

registerRoute(
    new NavigationRoute(createHandlerBoundToURL('/offline.html'), {
        denylist: [/^\/api\//, /^\/login/, /^\/register/, /^\/sanctum\//],
    }),
);

// Authenticated sync endpoints must never be served from a stale SW cache.
registerRoute(
    ({ url }) => url.pathname.startsWith('/api/sync/'),
    new NetworkOnly(),
    'GET',
);

self.addEventListener('sync', ((event: Event) => {
    const syncEvent = event as ExtendableEvent & { tag?: string };

    if (syncEvent.tag === 'kit-sync-outbox') {
        syncEvent.waitUntil(
            self.clients.matchAll({ type: 'window' }).then((clients) => {
                for (const client of clients) {
                    client.postMessage({ type: 'kit-flush-outbox' });
                }
            }),
        );
    }
}) as EventListener);

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        void self.skipWaiting();
    }
});
