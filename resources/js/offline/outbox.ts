import {
    getMeta,
    getOfflineDb,
    setMeta,
    withQuotaGuard
    
    
    
} from '@/offline/db';
import type {ConflictRecord, OutboxMutation, SyncStatus} from '@/offline/db';
import {
    getPullCursor,
    mergePulledItems,
    putCacheEntry,
    setPullCursor,
} from '@/offline/repository';
import { getOfflineSettings, registeredOfflineResources } from '@/offline/settings';

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function newUuid(): string {
    return crypto.randomUUID();
}

function authHeaders(token: string | null): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return headers;
}

function retryDelayMs(attempts: number): number {
    const base = Math.min(60_000, 1_000 * 2 ** Math.max(0, attempts - 1));

    return base;
}

export async function ensureDeviceId(): Promise<string> {
    const existing = await getMeta<string | null>('device_id', null);

    if (existing) {
        return existing;
    }

    const id = newUuid();
    await setMeta('device_id', id);

    return id;
}

export async function clearSyncToken(): Promise<void> {
    try {
        await setMeta('sync_token', null);
    } catch {
        // Database may already be unbound during logout.
    }
}

export async function ensureSyncToken(force = false): Promise<string | null> {
    if (! force) {
        const cached = await getMeta<string | null>('sync_token', null);

        if (cached) {
            return cached;
        }
    }

    const deviceId = await ensureDeviceId();

    const response = await fetch('/api/sync/token', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            ...authHeaders(null),
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            device_id: deviceId,
            device_name: navigator.userAgent.slice(0, 120),
        }),
    });

    if (! response.ok) {
        return null;
    }

    const data = (await response.json()) as { token: string; device_id: string };
    await setMeta('sync_token', data.token);
    await setMeta('device_id', data.device_id);

    return data.token;
}

export async function enqueueMutation(input: {
    resource: string;
    action: OutboxMutation['action'];
    body: Record<string, unknown>;
    clientUuid?: string;
}): Promise<OutboxMutation> {
    const settings = getOfflineSettings();
    const max = settings.max_outbox_size;
    const db = getOfflineDb();
    const count = await db.sync_outbox.where('status').anyOf(['pending', 'failed', 'syncing']).count();

    if (count >= max) {
        throw new Error('Offline outbox is full. Connect to sync pending items.');
    }

    const now = new Date().toISOString();
    const clientUuid =
        input.clientUuid ??
        (typeof input.body.client_uuid === 'string' ? input.body.client_uuid : newUuid());

    const row: OutboxMutation = {
        client_uuid: clientUuid,
        resource: input.resource,
        action: input.action,
        body: { ...input.body, client_uuid: clientUuid },
        client_updated_at: now,
        status: 'pending',
        attempts: 0,
        next_retry_at: null,
        last_error: null,
        created_at: now,
        updated_at: now,
    };

    const id = await withQuotaGuard(() => db.sync_outbox.add(row));

    await putCacheEntry({
        resource: input.resource,
        key: clientUuid,
        payload: { ...row.body, pending_local: true },
        pendingLocal: true,
        ttlSeconds: null,
    });

    return { ...row, id };
}

export async function pendingCount(): Promise<number> {
    return getOfflineDb().sync_outbox.where('status').anyOf(['pending', 'failed', 'syncing']).count();
}

export async function conflictCount(): Promise<number> {
    return getOfflineDb().conflicts.count();
}

export async function failedCount(): Promise<number> {
    return getOfflineDb().sync_outbox.where('status').equals('failed').count();
}

export async function listConflicts(): Promise<ConflictRecord[]> {
    return getOfflineDb().conflicts.orderBy('created_at').reverse().toArray();
}

export async function clearConflict(id: number): Promise<void> {
    await getOfflineDb().conflicts.delete(id);
}

export async function resolveConflictKeepLocal(id: number): Promise<void> {
    const db = getOfflineDb();
    const conflict = await db.conflicts.get(id);

    if (! conflict) {
        return;
    }

    const outbox = conflict.outbox_id
        ? await db.sync_outbox.get(conflict.outbox_id)
        : await db.sync_outbox.where('client_uuid').equals(conflict.client_uuid).first();

    if (outbox?.id) {
        await db.sync_outbox.update(outbox.id, {
            status: 'pending',
            last_error: null,
            next_retry_at: null,
            client_updated_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            body: {
                ...conflict.local,
                client_uuid: conflict.client_uuid,
                force_overwrite: true,
            },
        });
    } else {
        await enqueueMutation({
            resource: conflict.resource,
            action: 'upsert',
            body: {
                ...conflict.local,
                client_uuid: conflict.client_uuid,
                force_overwrite: true,
            },
            clientUuid: conflict.client_uuid,
        });
    }

    await db.conflicts.delete(id);
}

export async function resolveConflictUseServer(id: number): Promise<void> {
    const db = getOfflineDb();
    const conflict = await db.conflicts.get(id);

    if (! conflict) {
        return;
    }

    const outbox = conflict.outbox_id
        ? await db.sync_outbox.get(conflict.outbox_id)
        : await db.sync_outbox.where('client_uuid').equals(conflict.client_uuid).first();

    if (outbox?.id) {
        await db.sync_outbox.delete(outbox.id);
    }

    if (conflict.server && typeof conflict.server === 'object') {
        const server = conflict.server as { client_uuid?: string; updated_at?: string };
        await putCacheEntry({
            resource: conflict.resource,
            key: conflict.client_uuid,
            payload: conflict.server,
            updatedAt: server.updated_at ?? new Date().toISOString(),
            pendingLocal: false,
        });
    }

    await db.conflicts.delete(id);
}

type BatchResult = {
    client_uuid: string;
    status: SyncStatus | string;
    data?: unknown;
    message?: string | null;
    errors?: Record<string, string[]>;
};

async function pruneSynced(limit = 100): Promise<void> {
    const db = getOfflineDb();
    const synced = await db.sync_outbox.where('status').equals('synced').limit(limit).toArray();

    for (const row of synced) {
        if (row.id) {
            await db.sync_outbox.delete(row.id);
        }
    }
}

async function uploadPendingAttachments(
    token: string | null,
    deviceId: string,
    mutation: OutboxMutation,
): Promise<{ ok: boolean; error?: string }> {
    const settings = getOfflineSettings();

    if (! settings.attachments_enabled) {
        return { ok: true };
    }

    const db = getOfflineDb();
    const blobs = await db.attachment_blobs
        .where('client_uuid')
        .equals(mutation.client_uuid)
        .toArray();

    const pending = blobs.filter((blob) => (blob.upload_status ?? 'pending') !== 'uploaded');

    if (pending.length === 0) {
        return { ok: true };
    }

    const uploadedIds: Array<{ id: number; client_id: string }> = [];

    for (const blob of pending) {
        const form = new FormData();
        form.append('file', blob.blob, blob.name);
        form.append('client_uuid', blob.id);
        form.append('parent_client_uuid', mutation.client_uuid);
        form.append('resource', mutation.resource);
        form.append('device_id', deviceId);

        await db.attachment_blobs.update(blob.id, {
            upload_status: 'uploading',
            attempts: (blob.attempts ?? 0) + 1,
        });

        const response = await fetch('/api/sync/attachments', {
            method: 'POST',
            credentials: 'same-origin',
            headers: authHeaders(token),
            body: form,
        });

        if (! response.ok) {
            const message = `Attachment upload failed (HTTP ${response.status})`;
            await db.attachment_blobs.update(blob.id, {
                upload_status: 'failed',
                last_error: message,
            });

            return { ok: false, error: message };
        }

        const data = (await response.json()) as {
            attachment_id: number;
            client_uuid: string;
        };

        await db.attachment_blobs.update(blob.id, {
            upload_status: 'uploaded',
            server_attachment_id: data.attachment_id,
            last_error: null,
        });

        uploadedIds.push({ id: data.attachment_id, client_id: blob.id });
    }

    const existingMeta = Array.isArray(mutation.body.attachments_meta)
        ? (mutation.body.attachments_meta as Array<{ id: string | number }>)
        : [];

    const merged = [
        ...existingMeta.filter((item) => typeof item.id === 'number'),
        ...uploadedIds.map((item) => ({ id: item.id, client_id: item.client_id })),
    ];

    if (mutation.id) {
        await db.sync_outbox.update(mutation.id, {
            body: {
                ...mutation.body,
                attachments_meta: merged,
            },
            updated_at: new Date().toISOString(),
        });
        mutation.body.attachments_meta = merged;
    }

    for (const blob of pending) {
        await db.attachment_blobs.delete(blob.id);
    }

    return { ok: true };
}

export async function flushOutbox(): Promise<{
    flushed: number;
    failed: number;
    conflicts: number;
    durationMs: number;
}> {
    const started = performance.now();
    const settings = getOfflineSettings();
    const db = getOfflineDb();
    const now = Date.now();

    const candidates = await db.sync_outbox
        .where('status')
        .anyOf(['pending', 'failed'])
        .limit(settings.max_batch_size * 2)
        .toArray();

    const pending = candidates
        .filter((item) => {
            if (! item.next_retry_at) {
                return true;
            }

            return Date.parse(item.next_retry_at) <= now;
        })
        .slice(0, settings.max_batch_size);

    if (pending.length === 0) {
        await pruneSynced();

        return { flushed: 0, failed: 0, conflicts: 0, durationMs: 0 };
    }

    let token = await ensureSyncToken();
    const deviceId = await ensureDeviceId();

    for (const item of pending) {
        const upload = await uploadPendingAttachments(token, deviceId, item);

        if (! upload.ok && item.id) {
            await db.sync_outbox.update(item.id, {
                status: 'failed',
                last_error: upload.error ?? 'Attachment upload failed',
                next_retry_at: new Date(Date.now() + retryDelayMs(item.attempts + 1)).toISOString(),
                updated_at: new Date().toISOString(),
            });
        }
    }

    const ready = (
        await Promise.all(
            pending.map(async (item) => {
                const fresh = item.id ? await db.sync_outbox.get(item.id) : item;

                return fresh && fresh.status !== 'failed' ? fresh : null;
            }),
        )
    ).filter((item): item is OutboxMutation => item !== null);

    if (ready.length === 0) {
        return {
            flushed: 0,
            failed: pending.length,
            conflicts: 0,
            durationMs: Math.round(performance.now() - started),
        };
    }

    for (const item of ready) {
        if (item.id) {
            await db.sync_outbox.update(item.id, {
                status: 'syncing',
                attempts: item.attempts + 1,
                updated_at: new Date().toISOString(),
            });
        }
    }

    const headers: Record<string, string> = {
        ...authHeaders(token),
        'Content-Type': 'application/json',
    };

    let response = await fetch('/api/sync/batch', {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify({
            device_id: deviceId,
            mutations: ready.map((item) => ({
                client_uuid: item.client_uuid,
                resource: item.resource,
                action: item.action,
                body: item.body,
                client_updated_at: item.client_updated_at,
            })),
        }),
    });

    if (response.status === 401) {
        token = await ensureSyncToken(true);

        if (token) {
            headers.Authorization = `Bearer ${token}`;
        } else {
            delete headers.Authorization;
        }

        response = await fetch('/api/sync/batch', {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({
                device_id: deviceId,
                mutations: ready.map((item) => ({
                    client_uuid: item.client_uuid,
                    resource: item.resource,
                    action: item.action,
                    body: item.body,
                    client_updated_at: item.client_updated_at,
                })),
            }),
        });
    }

    if (! response.ok) {
        for (const item of ready) {
            if (item.id) {
                await db.sync_outbox.update(item.id, {
                    status: 'failed',
                    last_error: `HTTP ${response.status}`,
                    next_retry_at: new Date(Date.now() + retryDelayMs(item.attempts + 1)).toISOString(),
                    updated_at: new Date().toISOString(),
                });
            }
        }

        return {
            flushed: 0,
            failed: ready.length,
            conflicts: 0,
            durationMs: Math.round(performance.now() - started),
        };
    }

    const payload = (await response.json()) as { results: BatchResult[] };
    let flushed = 0;
    let failed = 0;
    let conflicts = 0;

    for (const result of payload.results ?? []) {
        const item = ready.find((row) => row.client_uuid === result.client_uuid);

        if (! item?.id) {
            continue;
        }

        if (result.status === 'processed' || result.status === 'synced') {
            await db.sync_outbox.update(item.id, {
                status: 'synced',
                last_error: null,
                next_retry_at: null,
                updated_at: new Date().toISOString(),
            });
            flushed += 1;

            if (result.data && typeof result.data === 'object') {
                await putCacheEntry({
                    resource: item.resource,
                    key: item.client_uuid,
                    payload: result.data,
                    pendingLocal: false,
                });
            }
        } else if (result.status === 'conflict') {
            conflicts += 1;
            await db.sync_outbox.update(item.id, {
                status: 'conflict',
                last_error: result.message ?? 'Conflict',
                updated_at: new Date().toISOString(),
            });
            await db.conflicts.add({
                client_uuid: item.client_uuid,
                resource: item.resource,
                outbox_id: item.id,
                local: item.body,
                server: result.data,
                message: result.message ?? undefined,
                created_at: new Date().toISOString(),
            });
        } else {
            failed += 1;
            await db.sync_outbox.update(item.id, {
                status: 'failed',
                last_error: result.message ?? 'Sync failed',
                next_retry_at: new Date(Date.now() + retryDelayMs(item.attempts + 1)).toISOString(),
                updated_at: new Date().toISOString(),
            });
        }
    }

    await pruneSynced();

    const durationMs = Math.round(performance.now() - started);

    if (settings.metrics_enabled) {
        void fetch('/api/sync/metrics', {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({
                flush_duration_ms: durationMs,
                outbox_count: await pendingCount(),
                failure_count: failed,
            }),
        }).catch(() => undefined);
    }

    await setMeta('last_flush_at', new Date().toISOString());

    return { flushed, failed, conflicts, durationMs };
}

export async function requestBackgroundSync(): Promise<void> {
    const registration = await navigator.serviceWorker?.ready;

    if (registration && 'sync' in registration) {
        try {
            await (
                registration as ServiceWorkerRegistration & {
                    sync: { register: (tag: string) => Promise<void> };
                }
            ).sync.register('kit-sync-outbox');
        } catch {
            // Fallback handled by online listeners.
        }
    }
}

export async function warmCache(): Promise<void> {
    const token = await ensureSyncToken();
    const response = await fetch('/api/sync/warm', {
        credentials: 'same-origin',
        headers: authHeaders(token),
    });

    if (! response.ok) {
        return;
    }

    const data = (await response.json()) as {
        org_settings: Record<string, unknown>;
        resources: Record<string, { items: unknown[] }>;
        warmed_at: string;
    };

    await setMeta('org_settings', data.org_settings);
    await setMeta('warmed_at', data.warmed_at);

    for (const [resource, payload] of Object.entries(data.resources ?? {})) {
        await mergePulledItems(
            resource,
            (payload.items ?? []) as Array<{
                client_uuid?: string;
                id?: number | string;
                updated_at?: string;
                deleted_at?: string | null;
            }>,
        );

        const last = (payload.items ?? []).at(-1) as { updated_at?: string } | undefined;

        if (last?.updated_at) {
            await setPullCursor(resource, last.updated_at);
        }
    }
}

export async function pullResource(resource: string, since?: string | null): Promise<void> {
    const token = await ensureSyncToken();
    const cursor = since ?? (await getPullCursor(resource));
    const params = new URLSearchParams({ resource });

    if (cursor) {
        params.set('since', cursor);
    }

    const response = await fetch(`/api/sync/pull?${params.toString()}`, {
        credentials: 'same-origin',
        headers: authHeaders(token),
    });

    if (! response.ok) {
        return;
    }

    const data = (await response.json()) as {
        items: Array<{
            client_uuid?: string;
            id?: number | string;
            updated_at?: string;
            deleted_at?: string | null;
        }>;
        cursor: string | null;
    };

    await mergePulledItems(resource, data.items ?? []);

    if (data.cursor) {
        await setPullCursor(resource, data.cursor);
    }
}

export async function pullAllResources(resources?: string[]): Promise<void> {
    const keys = resources ?? registeredOfflineResources();

    for (const resource of keys) {
        await pullResource(resource);
    }
}

export async function storeAttachmentBlob(input: {
    clientUuid: string;
    resource: string;
    file: File;
    maxBytes: number;
    allowedMimes?: string[];
}): Promise<string> {
    const settings = getOfflineSettings();

    if (! settings.attachments_enabled) {
        throw new Error('Offline attachments are disabled.');
    }

    if (input.file.size > input.maxBytes) {
        throw new Error(`Attachment exceeds ${input.maxBytes} bytes.`);
    }

    const allowed = input.allowedMimes ?? settings.allowed_mimes;

    if (allowed.length > 0 && input.file.type && ! allowed.includes(input.file.type)) {
        throw new Error(`Attachment type ${input.file.type} is not allowed.`);
    }

    const id = newUuid();

    await withQuotaGuard(() =>
        getOfflineDb().attachment_blobs.put({
            id,
            client_uuid: input.clientUuid,
            resource: input.resource,
            name: input.file.name,
            mime: input.file.type,
            size: input.file.size,
            blob: input.file,
            created_at: new Date().toISOString(),
            upload_status: 'pending',
            server_attachment_id: null,
            attempts: 0,
            last_error: null,
        }),
    );

    return id;
}

export async function getOfflineHealth(): Promise<{
    pending: number;
    failed: number;
    conflicts: number;
    lastFlushAt: string | null;
    warmedAt: string | null;
}> {
    return {
        pending: await pendingCount(),
        failed: await failedCount(),
        conflicts: await conflictCount(),
        lastFlushAt: await getMeta<string | null>('last_flush_at', null),
        warmedAt: await getMeta<string | null>('warmed_at', null),
    };
}
