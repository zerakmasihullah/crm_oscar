import { getMeta, getOfflineDb, setMeta  } from '@/offline/db';
import type {CacheEntry} from '@/offline/db';
import { getOfflineSettings } from '@/offline/settings';

function cacheKey(resource: string, key: string): string {
    return `${resource}:${key}`;
}

function isExpired(entry: CacheEntry): boolean {
    if (! entry.expires_at) {
        return false;
    }

    return new Date(entry.expires_at).getTime() <= Date.now();
}

export async function putCacheEntry(input: {
    resource: string;
    key: string;
    payload: unknown;
    updatedAt?: string;
    ttlSeconds?: number | null;
    pendingLocal?: boolean;
}): Promise<void> {
    const settings = getOfflineSettings();
    const ttl = input.ttlSeconds ?? settings.cache_ttl_seconds;
    const updatedAt = input.updatedAt ?? new Date().toISOString();
    const expiresAt =
        ttl && ttl > 0
            ? new Date(Date.parse(updatedAt) + ttl * 1000).toISOString()
            : null;

    await getOfflineDb().cache_entries.put({
        id: cacheKey(input.resource, input.key),
        resource: input.resource,
        payload: input.payload,
        updated_at: updatedAt,
        expires_at: expiresAt,
        pending_local: input.pendingLocal ?? false,
    });
}

export async function getCacheEntry<T = unknown>(
    resource: string,
    key: string,
): Promise<CacheEntry & { payload: T } | null> {
    const entry = await getOfflineDb().cache_entries.get(cacheKey(resource, key));

    if (! entry || isExpired(entry)) {
        return null;
    }

    return entry as CacheEntry & { payload: T };
}

export async function listCacheEntries<T = unknown>(
    resource: string,
    options: { includeExpired?: boolean; includePending?: boolean } = {},
): Promise<Array<CacheEntry & { payload: T }>> {
    const rows = await getOfflineDb().cache_entries.where('resource').equals(resource).toArray();

    return rows
        .filter((row) => {
            if (! options.includeExpired && isExpired(row)) {
                return false;
            }

            if (options.includePending === false && row.pending_local) {
                return false;
            }

            return true;
        })
        .sort((a, b) => b.updated_at.localeCompare(a.updated_at)) as Array<CacheEntry & { payload: T }>;
}

export async function removeCacheEntry(resource: string, key: string): Promise<void> {
    await getOfflineDb().cache_entries.delete(cacheKey(resource, key));
}

export async function pruneExpiredCache(): Promise<number> {
    const rows = await getOfflineDb().cache_entries.toArray();
    let removed = 0;

    for (const row of rows) {
        if (isExpired(row) && ! row.pending_local) {
            await getOfflineDb().cache_entries.delete(row.id);
            removed += 1;
        }
    }

    return removed;
}

export async function getPullCursor(resource: string): Promise<string | null> {
    return getMeta<string | null>(`pull_cursor:${resource}`, null);
}

export async function setPullCursor(resource: string, cursor: string | null): Promise<void> {
    await setMeta(`pull_cursor:${resource}`, cursor);
}

export async function mergePulledItems(
    resource: string,
    items: Array<Record<string, unknown> & {
        client_uuid?: string;
        id?: number | string;
        updated_at?: string;
        deleted_at?: string | null;
    }>,
): Promise<void> {
    const settings = getOfflineSettings();

    for (const item of items) {
        const key = item.client_uuid ?? String(item.id ?? '');

        if (! key) {
            continue;
        }

        const existing = await getCacheEntry(resource, key);

        if (existing?.pending_local) {
            continue;
        }

        if (item.deleted_at) {
            await removeCacheEntry(resource, key);
            continue;
        }

        await putCacheEntry({
            resource,
            key,
            payload: item,
            updatedAt: item.updated_at ?? new Date().toISOString(),
            ttlSeconds: settings.cache_ttl_seconds,
            pendingLocal: false,
        });
    }
}

export type OfflineResourceItem = {
    id?: number;
    client_uuid: string;
    title?: string;
    name?: string;
    status?: string;
    location?: string | null;
    updated_at?: string;
    deleted_at?: string | null;
    pending_local?: boolean;
    [key: string]: unknown;
};

export async function listOfflineResource<T extends OfflineResourceItem = OfflineResourceItem>(
    resource: string,
): Promise<T[]> {
    const entries = await listCacheEntries<T>(resource, {
        includePending: true,
    });

    return entries.map((entry) => ({
        ...entry.payload,
        client_uuid: entry.payload.client_uuid,
        pending_local: entry.pending_local ?? false,
        updated_at: entry.updated_at,
    }));
}

/** @deprecated Use listOfflineResource('field_reports') */
export type FieldReportCacheItem = OfflineResourceItem & {
    title: string;
    body?: string | null;
    location?: string | null;
    status?: string;
    attachments_meta?: Array<{ id: string | number }> | null;
};

/** @deprecated Use listOfflineResource('field_reports') */
export async function listOfflineFieldReports(): Promise<FieldReportCacheItem[]> {
    return listOfflineResource<FieldReportCacheItem>('field_reports');
}
