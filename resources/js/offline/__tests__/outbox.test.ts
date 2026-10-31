import { describe, expect, it, vi } from 'vitest';
import { bindOfflineUser, getOfflineDb } from '@/offline/db';
import {
    enqueueMutation,
    flushOutbox,
    pendingCount,
    resolveConflictKeepLocal,
    resolveConflictUseServer,
} from '@/offline/outbox';
import { listCacheEntries } from '@/offline/repository';
import { setOfflineSettingsCache } from '@/offline/settings';

describe('offline outbox', () => {
    it('honors max outbox size from settings', async () => {
        setOfflineSettingsCache({ max_outbox_size: 1, enabled: true });
        await bindOfflineUser(11);

        await enqueueMutation({
            resource: 'field_reports',
            action: 'upsert',
            body: { title: 'One' },
        });

        await expect(
            enqueueMutation({
                resource: 'field_reports',
                action: 'upsert',
                body: { title: 'Two' },
            }),
        ).rejects.toThrow(/outbox is full/i);

        expect(await pendingCount()).toBe(1);
    });

    it('flushes pending mutations and prunes synced rows', async () => {
        await bindOfflineUser(12);
        setOfflineSettingsCache({
            enabled: true,
            max_batch_size: 10,
            max_outbox_size: 50,
            metrics_enabled: false,
            attachments_enabled: false,
        });

        const mutation = await enqueueMutation({
            resource: 'field_reports',
            action: 'upsert',
            body: { title: 'Queued' },
            clientUuid: '11111111-1111-4111-8111-111111111111',
        });

        vi.stubGlobal(
            'fetch',
            vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
                const url = String(input);

                if (url.includes('/api/sync/token')) {
                    return new Response(
                        JSON.stringify({
                            token: 'test-token',
                            device_id: '22222222-2222-4222-8222-222222222222',
                        }),
                        { status: 200, headers: { 'Content-Type': 'application/json' } },
                    );
                }

                if (url.includes('/api/sync/batch')) {
                    const body = JSON.parse(String(init?.body ?? '{}')) as {
                        mutations: Array<{ client_uuid: string }>;
                    };

                    return new Response(
                        JSON.stringify({
                            results: body.mutations.map((item) => ({
                                client_uuid: item.client_uuid,
                                status: 'processed',
                                data: {
                                    client_uuid: item.client_uuid,
                                    title: 'Queued',
                                    id: 99,
                                },
                            })),
                        }),
                        { status: 200, headers: { 'Content-Type': 'application/json' } },
                    );
                }

                return new Response('not found', { status: 404 });
            }),
        );

        const result = await flushOutbox();

        expect(result.flushed).toBe(1);
        expect(await pendingCount()).toBe(0);
        expect(await getOfflineDb().sync_outbox.get(mutation.id!)).toBeUndefined();

        const cached = await listCacheEntries('field_reports');
        expect(cached).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it('can resolve conflicts by keeping local or using server', async () => {
        await bindOfflineUser(13);
        const db = getOfflineDb();

        const outboxId = await db.sync_outbox.add({
            client_uuid: '33333333-3333-4333-8333-333333333333',
            resource: 'field_reports',
            action: 'upsert',
            body: { title: 'Local' },
            client_updated_at: new Date().toISOString(),
            status: 'conflict',
            attempts: 1,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        });

        const conflictId = await db.conflicts.add({
            client_uuid: '33333333-3333-4333-8333-333333333333',
            resource: 'field_reports',
            outbox_id: outboxId,
            local: { title: 'Local' },
            server: { title: 'Server', client_uuid: '33333333-3333-4333-8333-333333333333' },
            created_at: new Date().toISOString(),
        });

        expect(conflictId).toBeTypeOf('number');
        await resolveConflictKeepLocal(conflictId as number);
        const pending = await db.sync_outbox.get(outboxId);
        expect(pending?.status).toBe('pending');
        expect(pending?.body.force_overwrite).toBe(true);

        const conflictId2 = await db.conflicts.add({
            client_uuid: '33333333-3333-4333-8333-333333333333',
            resource: 'field_reports',
            outbox_id: outboxId,
            local: { title: 'Local' },
            server: {
                title: 'Server',
                client_uuid: '33333333-3333-4333-8333-333333333333',
                updated_at: new Date().toISOString(),
            },
            created_at: new Date().toISOString(),
        });

        expect(conflictId2).toBeTypeOf('number');
        await resolveConflictUseServer(conflictId2 as number);
        expect(await db.sync_outbox.get(outboxId)).toBeUndefined();
        const cached = await listCacheEntries<{ title: string }>('field_reports');
        expect(cached[0]?.payload.title).toBe('Server');
    });
});
