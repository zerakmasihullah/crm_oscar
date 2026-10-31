import { describe, expect, it } from 'vitest';
import { bindOfflineUser } from '@/offline/db';
import { listOfflineResource, putCacheEntry } from '@/offline/repository';
import {
    registeredOfflineResources,
    setOfflineSettingsCache,
} from '@/offline/settings';

describe('generic offline resources', () => {
    it('lists any resource key from the cache', async () => {
        await bindOfflineUser(31);

        await putCacheEntry({
            resource: 'field_notes',
            key: 'note-1',
            payload: { client_uuid: 'note-1', name: 'Alpha' },
            pendingLocal: true,
            ttlSeconds: null,
        });

        const items = await listOfflineResource('field_notes');

        expect(items).toHaveLength(1);
        expect(items[0]?.name).toBe('Alpha');
        expect(items[0]?.pending_local).toBe(true);
    });

    it('resolves warm/resources lists from settings for pullAllResources', () => {
        setOfflineSettingsCache({
            resources: ['field_reports', 'field_notes'],
            warm_resources: ['field_notes'],
        });

        expect(registeredOfflineResources()).toEqual(['field_notes']);

        setOfflineSettingsCache({
            resources: ['field_reports', 'field_notes'],
            warm_resources: [],
        });

        expect(registeredOfflineResources()).toEqual(['field_reports', 'field_notes']);
    });
});
