import {
    OFFLINE_USER_KEY,
    bindOfflineUser,
    deleteOfflineDb,
    getActiveUserId,
    unbindOfflineUser,
} from '@/offline/db';

export type OfflineUserIdentity = {
    id: number;
    org_id?: number | null;
};

function readStoredUserId(): number | null {
    if (typeof localStorage === 'undefined') {
        return null;
    }

    const raw = localStorage.getItem(OFFLINE_USER_KEY);

    if (! raw) {
        return null;
    }

    const parsed = Number(raw);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function storeUserId(userId: number | null): void {
    if (typeof localStorage === 'undefined') {
        return;
    }

    if (userId === null) {
        localStorage.removeItem(OFFLINE_USER_KEY);

        return;
    }

    localStorage.setItem(OFFLINE_USER_KEY, String(userId));
}

export async function onLogin(user: OfflineUserIdentity): Promise<void> {
    const previous = readStoredUserId();

    if (previous !== null && previous !== user.id) {
        await deleteOfflineDb(previous);
    }

    await bindOfflineUser(user.id, user.org_id);
    storeUserId(user.id);
}

export async function onLogout(userId?: number | null): Promise<void> {
    const id = userId ?? getActiveUserId() ?? readStoredUserId();

    if (id !== null) {
        await deleteOfflineDb(id);
    }

    await unbindOfflineUser();
    storeUserId(null);
}

export async function ensureUserScope(user: OfflineUserIdentity | null): Promise<boolean> {
    if (! user) {
        if (getActiveUserId() !== null || readStoredUserId() !== null) {
            await onLogout();
        }

        return false;
    }

    const previous = readStoredUserId();

    if (previous !== null && previous !== user.id) {
        await onLogout(previous);
    }

    await onLogin(user);

    return true;
}
