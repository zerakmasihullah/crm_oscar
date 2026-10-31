import { computed, readonly, ref } from 'vue';
import {
    getCurrentUser,
    login as apiLogin,
    loginWithToken as apiLoginWithToken,
    logout as apiLogout,
    register as apiRegister,
    updateProfile as apiUpdateProfile,
    validateUserSession,
    type User,
} from '@/lib/storefront/api';

const REMEMBER_EMAIL_KEY = 'storefront_login_email';

const user = ref<User | null>(null);
const ready = ref(false);
let listenersBound = false;

function syncFromStorage() {
    const session = validateUserSession();
    user.value = session.valid ? session.user : null;
    ready.value = true;
}

function emitAuth(event: 'auth:login' | 'auth:logout') {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(event));
    }
}

function bindListeners() {
    if (listenersBound || typeof window === 'undefined') {
        return;
    }
    listenersBound = true;
    window.addEventListener('auth:login', syncFromStorage);
    window.addEventListener('auth:logout', () => {
        user.value = null;
        ready.value = true;
    });
    window.addEventListener('storage', (e: StorageEvent) => {
        if (e.key === 'auth_token' || e.key === 'user' || e.key === 'token_expiry') {
            syncFromStorage();
        }
    });
}

export function useStorefrontAuth() {
    bindListeners();
    if (!ready.value) {
        syncFromStorage();
    }

    const isAuthenticated = computed(() => !!user.value);

    async function login(email: string, password: string) {
        const data = await apiLogin(email, password);
        user.value = data.user ?? getCurrentUser();
        try {
            localStorage.setItem(REMEMBER_EMAIL_KEY, email.trim().toLowerCase());
        } catch {
            // ignore
        }
        emitAuth('auth:login');
        return data;
    }

    async function loginWithToken(token: string) {
        const next = await apiLoginWithToken(token);
        user.value = next;
        try {
            if (next.email) {
                localStorage.setItem(REMEMBER_EMAIL_KEY, String(next.email).trim().toLowerCase());
            }
        } catch {
            // ignore
        }
        emitAuth('auth:login');
        return next;
    }

    async function register(payload: {
        name: string;
        email: string;
        password: string;
        phone_number?: string;
        area_code?: string;
        address?: string;
        latitude?: string;
        longitude?: string;
    }) {
        await apiRegister(payload);
        // Auto-login so register feels like one step
        try {
            return await login(payload.email, payload.password);
        } catch {
            try {
                localStorage.setItem(REMEMBER_EMAIL_KEY, payload.email.trim().toLowerCase());
            } catch {
                // ignore
            }
            throw new Error('Account created. Please log in with your new password.');
        }
    }

    async function logout() {
        try {
            await apiLogout();
        } finally {
            user.value = null;
            // apiLogout already emits; emit again only if needed for local sync
            if (typeof window !== 'undefined') {
                // no-op: listeners already cleared storage
            }
        }
    }

    async function updateProfile(
        userId: number,
        payload: {
            name: string;
            email: string;
            phone_number?: string;
            area_code?: string;
            latitude?: string;
            longitude?: string;
            address?: string;
            password?: string;
        },
    ) {
        await apiUpdateProfile(userId, payload);
        user.value = getCurrentUser();
        emitAuth('auth:login');
        return user.value;
    }

    function rememberedEmail(): string {
        try {
            return localStorage.getItem(REMEMBER_EMAIL_KEY) || '';
        } catch {
            return '';
        }
    }

    return {
        user: readonly(user),
        ready: readonly(ready),
        isAuthenticated,
        login,
        loginWithToken,
        register,
        logout,
        updateProfile,
        syncFromStorage,
        rememberedEmail,
    };
}
