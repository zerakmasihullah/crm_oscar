import Dexie from 'dexie';
import type {EntityTable} from 'dexie';

export type SyncStatus = 'pending' | 'syncing' | 'synced' | 'failed' | 'conflict';

export type AttachmentUploadStatus = 'pending' | 'uploading' | 'uploaded' | 'failed';

export type OutboxMutation = {
    id?: number;
    client_uuid: string;
    resource: string;
    action: 'create' | 'update' | 'delete' | 'upsert';
    body: Record<string, unknown>;
    client_updated_at: string;
    status: SyncStatus;
    attempts: number;
    next_retry_at?: string | null;
    last_error?: string | null;
    created_at: string;
    updated_at: string;
};

export type CacheEntry = {
    id: string;
    resource: string;
    payload: unknown;
    updated_at: string;
    expires_at?: string | null;
    pending_local?: boolean;
};

export type SyncMeta = {
    key: string;
    value: unknown;
};

export type AttachmentBlob = {
    id: string;
    client_uuid: string;
    resource: string;
    name: string;
    mime: string;
    size: number;
    blob: Blob;
    created_at: string;
    upload_status?: AttachmentUploadStatus;
    server_attachment_id?: number | null;
    last_error?: string | null;
    attempts?: number;
};

export type ConflictRecord = {
    id?: number;
    client_uuid: string;
    resource: string;
    outbox_id?: number | null;
    local: Record<string, unknown>;
    server: unknown;
    message?: string;
    created_at: string;
};

export class KitOfflineDatabase extends Dexie {
    sync_outbox!: EntityTable<OutboxMutation, 'id'>;
    cache_entries!: EntityTable<CacheEntry, 'id'>;
    sync_meta!: EntityTable<SyncMeta, 'key'>;
    attachment_blobs!: EntityTable<AttachmentBlob, 'id'>;
    conflicts!: EntityTable<ConflictRecord, 'id'>;

    constructor(name: string) {
        super(name);

        this.version(1).stores({
            sync_outbox: '++id, client_uuid, resource, status, created_at',
            cache_entries: 'id, resource, updated_at',
            sync_meta: 'key',
            attachment_blobs: 'id, client_uuid, resource, created_at',
            conflicts: '++id, client_uuid, resource, created_at',
        });

        this.version(2)
            .stores({
                sync_outbox: '++id, client_uuid, resource, status, created_at, next_retry_at',
                cache_entries: 'id, resource, updated_at, expires_at, pending_local',
                sync_meta: 'key',
                attachment_blobs: 'id, client_uuid, resource, created_at, upload_status',
                conflicts: '++id, client_uuid, resource, outbox_id, created_at',
            })
            .upgrade(async (tx) => {
                await tx
                    .table('attachment_blobs')
                    .toCollection()
                    .modify((row: AttachmentBlob) => {
                        row.upload_status = row.upload_status ?? 'pending';
                        row.attempts = row.attempts ?? 0;
                        row.server_attachment_id = row.server_attachment_id ?? null;
                    });
            });
    }
}

export const LEGACY_OFFLINE_DB_NAME = 'yaqeen_kit_offline';
export const OFFLINE_USER_KEY = 'kit_offline_user_id';

let activeDb: KitOfflineDatabase | null = null;
let activeUserId: number | null = null;

export function dbNameForUser(userId: number, orgId?: number | null): string {
    if (orgId) {
        return `yaqeen_kit_offline_o${orgId}_u${userId}`;
    }

    return `yaqeen_kit_offline_u${userId}`;
}

export function getActiveUserId(): number | null {
    return activeUserId;
}

export function getOfflineDb(): KitOfflineDatabase {
    if (! activeDb || activeUserId === null) {
        throw new Error('Offline database is not bound to a user. Call bindOfflineUser first.');
    }

    return activeDb;
}

/** @deprecated Use getOfflineDb() after bindOfflineUser(). Kept for gradual migration. */
export const offlineDb = new Proxy({} as KitOfflineDatabase, {
    get(_target, prop, receiver) {
        const db = getOfflineDb();

        const value = Reflect.get(db, prop, receiver);

        return typeof value === 'function' ? value.bind(db) : value;
    },
});

export async function deleteLegacyOfflineDb(): Promise<void> {
    if (await Dexie.exists(LEGACY_OFFLINE_DB_NAME)) {
        await Dexie.delete(LEGACY_OFFLINE_DB_NAME);
    }
}

export async function deleteOfflineDb(userId: number, orgId?: number | null): Promise<void> {
    const name = dbNameForUser(userId, orgId);

    if (activeUserId === userId && activeDb) {
        activeDb.close();
        activeDb = null;
        activeUserId = null;
    }

    if (await Dexie.exists(name)) {
        await Dexie.delete(name);
    }
}

export async function bindOfflineUser(userId: number, orgId?: number | null): Promise<KitOfflineDatabase> {
    await deleteLegacyOfflineDb();

    if (activeUserId === userId && activeDb) {
        return activeDb;
    }

    if (activeDb) {
        activeDb.close();
        activeDb = null;
    }

    activeUserId = userId;
    activeDb = new KitOfflineDatabase(dbNameForUser(userId, orgId));
    await activeDb.open();

    return activeDb;
}

export async function unbindOfflineUser(): Promise<void> {
    if (activeDb) {
        activeDb.close();
        activeDb = null;
    }

    activeUserId = null;
}

export async function getMeta<T>(key: string, fallback: T): Promise<T> {
    const row = await getOfflineDb().sync_meta.get(key);

    return (row?.value as T | undefined) ?? fallback;
}

export async function setMeta(key: string, value: unknown): Promise<void> {
    await getOfflineDb().sync_meta.put({ key, value });
}

export async function estimateStorage(): Promise<{
    usage: number;
    quota: number;
    percent: number | null;
}> {
    if (! ('storage' in navigator) || ! navigator.storage?.estimate) {
        return { usage: 0, quota: 0, percent: null };
    }

    const estimate = await navigator.storage.estimate();
    const usage = estimate.usage ?? 0;
    const quota = estimate.quota ?? 0;

    return {
        usage,
        quota,
        percent: quota > 0 ? Math.round((usage / quota) * 100) : null,
    };
}

export function isQuotaExceededError(error: unknown): boolean {
    if (! error || typeof error !== 'object') {
        return false;
    }

    const name = 'name' in error ? String(error.name) : '';

    return name === 'QuotaExceededError' || name === 'NS_ERROR_DOM_QUOTA_REACHED';
}

export async function withQuotaGuard<T>(operation: () => Promise<T>): Promise<T> {
    try {
        return await operation();
    } catch (error) {
        if (isQuotaExceededError(error)) {
            throw new Error('Device storage is full. Sync or delete offline data, then try again.');
        }

        throw error;
    }
}
