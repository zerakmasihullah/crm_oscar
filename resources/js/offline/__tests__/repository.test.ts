import { describe, expect, it } from 'vitest';
import { bindOfflineUser } from '@/offline/db';
import {
    getCacheEntry,
    listCacheEntries,
    mergePulledItems,
    pruneExpiredCache,
    putCacheEntry,
} from '@/offline/repository';
import { setOfflineSettingsCache } from '@/offline/settings';

describe('offline repository', () => {
    it('stores and lists resource entries with ttl', async () => {
        await bindOfflineUser(21);
        setOfflineSettingsCache({ cache_ttl_seconds: 1 });

        await putCacheEntry({
            resource: 'field_reports',
            key: 'aaaa',
            payload: { client_uuid: 'aaaa', title: 'Fresh' },
            updatedAt: new Date().toISOString(),
            ttlSeconds: 3600,
        });

        await putCacheEntry({
            resource: 'field_reports',
            key: 'bbbb',
            payload: { client_uuid: 'bbbb', title: 'Stale' },
            updatedAt: new Date(Date.now() - 10_000).toISOString(),
            ttlSeconds: 1,
        });

        expect(await getCacheEntry('field_reports', 'aaaa')).not.toBeNull();
        expect(await getCacheEntry('field_reports', 'bbbb')).toBeNull();

        const removed = await pruneExpiredCache();
        expect(removed).toBeGreaterThanOrEqual(1);
        expect(await listCacheEntries('field_reports')).toHaveLength(1);
    });

    it('does not overwrite pending local edits during pull merge', async () => {
        await bindOfflineUser(22);

        await putCacheEntry({
            resource: 'field_reports',
            key: 'cccc',
            payload: { client_uuid: 'cccc', title: 'Local draft' },
            pendingLocal: true,
            ttlSeconds: null,
        });

        await mergePulledItems('field_reports', [
            {
                client_uuid: 'cccc',
                title: 'Server copy',
                updated_at: new Date().toISOString(),
            },
        ]);

        const entry = await getCacheEntry<{ title: string }>('field_reports', 'cccc');
        expect(entry?.payload.title).toBe('Local draft');
        expect(entry?.pending_local).toBe(true);
    });
});
