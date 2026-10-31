import 'fake-indexeddb/auto';
import Dexie from 'dexie';
import { afterEach } from 'vitest';
import { unbindOfflineUser } from '@/offline/db';
import { setOfflineSettingsCache } from '@/offline/settings';

afterEach(async () => {
    await unbindOfflineUser();

    const names = await Dexie.getDatabaseNames();

    await Promise.all(names.map((name) => Dexie.delete(name)));

    setOfflineSettingsCache({
        enabled: true,
        max_batch_size: 50,
        max_outbox_size: 500,
        attachments_enabled: true,
        metrics_enabled: false,
        allowed_mimes: ['image/jpeg', 'image/png', 'application/pdf'],
        attachment_max_bytes: 2_097_152,
        cache_ttl_seconds: 3600,
    });
});
