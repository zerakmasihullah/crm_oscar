import { describe, expect, it } from 'vitest';
import {
    bindOfflineUser,
    dbNameForUser,
    deleteOfflineDb,
    getActiveUserId,
    getMeta,
    getOfflineDb,
    setMeta,
} from '@/offline/db';
import { ensureUserScope, onLogout } from '@/offline/lifecycle';

describe('offline database lifecycle', () => {
    it('namespaces databases per user', async () => {
        expect(dbNameForUser(7)).toBe('yaqeen_kit_offline_u7');
        expect(dbNameForUser(7, 3)).toBe('yaqeen_kit_offline_o3_u7');

        await bindOfflineUser(7);
        await setMeta('device_id', 'abc');

        expect(getActiveUserId()).toBe(7);
        expect(await getMeta('device_id', null)).toBe('abc');
        expect(getOfflineDb().name).toBe('yaqeen_kit_offline_u7');
    });

    it('wipes previous user data on user switch', async () => {
        await ensureUserScope({ id: 1 });
        await setMeta('sync_token', 'user-1-token');

        await ensureUserScope({ id: 2 });
        expect(getActiveUserId()).toBe(2);
        expect(await getMeta('sync_token', null)).toBeNull();

        await deleteOfflineDb(1);
    });

    it('clears database on logout', async () => {
        await ensureUserScope({ id: 9 });
        await setMeta('device_id', 'device-9');
        await onLogout(9);

        expect(getActiveUserId()).toBeNull();
        expect(() => getOfflineDb()).toThrow();
    });
});
