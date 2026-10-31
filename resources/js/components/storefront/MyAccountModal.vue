<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Eye,
    EyeOff,
    Headset,
    KeyRound,
    LogOut,
    ShoppingBag,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, defineAsyncComponent, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import SocialAuthButtons from '@/components/storefront/SocialAuthButtons.vue';
import ThemeToggle from '@/components/storefront/ThemeToggle.vue';
import { useStorefrontAuth } from '@/composables/storefront/useStorefrontAuth';
import { redirectToBillingPortal } from '@/lib/storefront/api';

const LocationPicker = defineAsyncComponent(
    () => import('@/components/storefront/LocationPicker.vue'),
);

const props = defineProps<{ isOpen: boolean }>();
const emit = defineEmits<{ close: [] }>();

const auth = useStorefrontAuth();
const user = computed(() => auth.user.value);

type AuthMode = 'login' | 'register';
type Panel = 'menu' | 'profile' | 'password';

const mode = ref<AuthMode>('login');
const registerStep = ref(1);
const panel = ref<Panel>('menu');
const loading = ref(false);
const error = ref<string | null>(null);
const success = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});
const showPassword = ref(false);

const editName = ref('');
const editEmail = ref('');
const editPhone = ref('');
const editAreaCode = ref('');
const editAddress = ref('');
const editLatitude = ref('');
const editLongitude = ref('');

const loginEmail = ref('');
const loginPassword = ref('');
const loginEmailInput = ref<HTMLInputElement | null>(null);

const registerName = ref('');
const registerEmail = ref('');
const registerPassword = ref('');
const registerPhone = ref('');
const registerAreaCode = ref('');
const registerAddress = ref('');

const newPassword = ref('');
const confirmPassword = ref('');

const title = computed(() => {
    if (!user.value) {
        if (mode.value === 'register') {
            return registerStep.value === 1 ? 'Create account' : 'Almost done';
        }
        return 'Log in';
    }
    if (panel.value === 'profile') return 'Edit profile';
    if (panel.value === 'password') return 'Change password';
    return 'My account';
});

const inputClass =
    'w-full h-11 px-3.5 bg-background text-foreground rounded-xl border border-border focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition text-sm';
const inputErrorClass =
    'w-full h-11 px-3.5 bg-background text-foreground rounded-xl border border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20 transition text-sm';

function resetMessages() {
    error.value = null;
    success.value = null;
    fieldErrors.value = {};
}

function resetAuthForms() {
    loginPassword.value = '';
    registerName.value = '';
    registerEmail.value = '';
    registerPassword.value = '';
    registerPhone.value = '';
    registerAreaCode.value = '';
    registerAddress.value = '';
    registerStep.value = 1;
    showPassword.value = false;
    panel.value = 'menu';
    newPassword.value = '';
    confirmPassword.value = '';
    resetMessages();
}

function clearFieldError(key: string) {
    if (fieldErrors.value[key]) {
        const next = { ...fieldErrors.value };
        delete next[key];
        fieldErrors.value = next;
    }
}

function switchMode(next: AuthMode) {
    mode.value = next;
    registerStep.value = 1;
    resetMessages();
    showPassword.value = false;
}

function goRegisterStep2() {
    resetMessages();
    if (!registerName.value.trim() || registerName.value.trim().length < 2) {
        fieldErrors.value = { name: 'Enter your name' };
        return;
    }
    if (!registerEmail.value.trim() || !registerEmail.value.includes('@')) {
        fieldErrors.value = { email: 'Enter a valid email' };
        return;
    }
    registerStep.value = 2;
}

watch(
    () => props.isOpen,
    async (isOpen) => {
        if (!isOpen) return;
        auth.syncFromStorage();
        mode.value = 'login';
        resetAuthForms();
        loginEmail.value = auth.rememberedEmail();
        await nextTick();
        if (!user.value) loginEmailInput.value?.focus();
    },
);

watch(panel, (next) => {
    if (next === 'profile' && user.value) {
        editName.value = user.value.name || '';
        editEmail.value = user.value.email || '';
        editPhone.value = user.value.phone_number || '';
        editAreaCode.value = user.value.area_code || '';
        editAddress.value = user.value.address || '';
        editLatitude.value = user.value.latitude || '';
        editLongitude.value = user.value.longitude || '';
    }
    if (next === 'password') {
        newPassword.value = '';
        confirmPassword.value = '';
        showPassword.value = false;
    }
    resetMessages();
});

async function handleLogin(e: Event) {
    e.preventDefault();
    if (loading.value) return;
    loading.value = true;
    resetMessages();
    try {
        await auth.login(loginEmail.value, loginPassword.value);
        loginPassword.value = '';
        panel.value = 'menu';
    } catch (err: unknown) {
        error.value = err instanceof Error ? err.message : 'Login failed. Please check your credentials.';
    } finally {
        loading.value = false;
    }
}

async function handleRegister(e: Event) {
    e.preventDefault();
    if (loading.value) return;
    if (registerStep.value === 1) {
        goRegisterStep2();
        return;
    }

    loading.value = true;
    resetMessages();

    if (!registerPassword.value || registerPassword.value.length < 6) {
        fieldErrors.value = { password: 'Use at least 6 characters' };
        loading.value = false;
        return;
    }

    try {
        const address = registerAddress.value.trim();
        const areaCode = registerAreaCode.value.trim();
        await auth.register({
            name: registerName.value,
            email: registerEmail.value,
            password: registerPassword.value,
            phone_number: registerPhone.value.trim() || undefined,
            area_code: areaCode || undefined,
            address: address || undefined,
        });
        if (address) {
            try {
                localStorage.setItem('user_address', address);
            } catch {
                // ignore
            }
        }
        resetAuthForms();
        panel.value = 'menu';
    } catch (err: unknown) {
        const e = err as { errors?: Record<string, string[]>; message?: string };
        if (e.errors && typeof e.errors === 'object') {
            const errors: Record<string, string> = {};
            Object.keys(e.errors).forEach((key) => {
                const arr = e.errors![key];
                if (Array.isArray(arr) && arr.length > 0) errors[key] = arr[0];
            });
            fieldErrors.value = errors;
            error.value = e.message || 'Please check the fields below.';
            if (errors.name || errors.email) registerStep.value = 1;
        } else if (e.message?.includes('Account created')) {
            mode.value = 'login';
            registerStep.value = 1;
            loginEmail.value = registerEmail.value || auth.rememberedEmail();
            success.value = e.message;
        } else {
            error.value = e.message || 'Registration failed. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}

async function handleLogout() {
    loading.value = true;
    try {
        await auth.logout();
        mode.value = 'login';
        panel.value = 'menu';
    } finally {
        loading.value = false;
    }
}

async function handleUpdateProfile(e: Event) {
    e.preventDefault();
    if (!user.value || loading.value) return;
    loading.value = true;
    resetMessages();

    try {
        const address = editAddress.value.trim();
        await auth.updateProfile(user.value.id, {
            name: editName.value,
            email: editEmail.value,
            phone_number: editPhone.value || undefined,
            area_code: editAreaCode.value || undefined,
            address: address || undefined,
            latitude: editLatitude.value || undefined,
            longitude: editLongitude.value || undefined,
        });
        if (address) {
            try {
                localStorage.setItem('user_address', address);
            } catch {
                // ignore
            }
        }
        panel.value = 'menu';
        success.value = 'Profile updated';
    } catch (err: unknown) {
        const message = err instanceof Error ? err.message : 'Failed to update profile.';
        if (message.includes('Session expired') || message.includes('401')) {
            error.value = 'Your session has expired. Please log in again.';
            mode.value = 'login';
            panel.value = 'menu';
            await auth.logout();
        } else {
            error.value = message;
        }
    } finally {
        loading.value = false;
    }
}

async function handleChangePassword(e: Event) {
    e.preventDefault();
    if (!user.value || loading.value) return;
    resetMessages();

    if (newPassword.value.length < 6) {
        fieldErrors.value = { password: 'Use at least 6 characters' };
        return;
    }
    if (newPassword.value !== confirmPassword.value) {
        fieldErrors.value = { password: 'Passwords do not match' };
        return;
    }

    loading.value = true;
    try {
        await auth.updateProfile(user.value.id, {
            name: user.value.name,
            email: user.value.email,
            phone_number: user.value.phone_number || undefined,
            area_code: user.value.area_code || undefined,
            latitude: user.value.latitude || undefined,
            longitude: user.value.longitude || undefined,
            password: newPassword.value,
        });
        newPassword.value = '';
        confirmPassword.value = '';
        panel.value = 'menu';
        success.value = 'Password updated';
    } catch (err: unknown) {
        error.value = err instanceof Error ? err.message : 'Could not change password.';
    } finally {
        loading.value = false;
    }
}

function handleViewOrders() {
    emit('close');
    router.visit('/my-orders');
}

function handleSupport() {
    emit('close');
    router.visit('/support');
}

async function handleBillingPortal() {
    try {
        await redirectToBillingPortal();
    } catch (err: unknown) {
        error.value = err instanceof Error ? err.message : 'Failed to open billing portal.';
    }
}

function onAuthError(e: Event) {
    const detail = (e as CustomEvent<string>).detail;
    if (detail) error.value = detail;
}

onMounted(() => {
    window.addEventListener('storefront:auth-error', onAuthError);
    try {
        const stored = sessionStorage.getItem('storefront_auth_error');
        if (stored) {
            error.value = stored;
            sessionStorage.removeItem('storefront_auth_error');
        }
    } catch {
        // ignore
    }
});

onUnmounted(() => {
    window.removeEventListener('storefront:auth-error', onAuthError);
});

function goBack() {
    if (!user.value && mode.value === 'register' && registerStep.value === 2) {
        registerStep.value = 1;
        resetMessages();
        return;
    }
    if (user.value && panel.value !== 'menu') {
        panel.value = 'menu';
        resetMessages();
        return;
    }
    emit('close');
}

const showBack = computed(() => {
    if (!user.value && mode.value === 'register' && registerStep.value === 2) return true;
    if (user.value && panel.value !== 'menu') return true;
    return false;
});
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 p-0 backdrop-blur-sm sm:items-center sm:p-4 dark:bg-black/75"
        @click="emit('close')"
    >
        <div
            class="flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl border border-border bg-card shadow-2xl sm:max-h-[88vh] sm:rounded-3xl"
            @click.stop
        >
            <div class="flex justify-center pt-3 sm:hidden">
                <div class="h-1 w-10 rounded-full bg-border" />
            </div>

            <div class="flex items-center justify-between gap-2 border-b border-border px-4 pb-3 pt-2 sm:px-5 sm:pt-4">
                <div class="flex min-w-0 items-center gap-1">
                    <button
                        v-if="showBack"
                        type="button"
                        class="rounded-full p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        aria-label="Back"
                        @click="goBack"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <h2 class="truncate text-lg font-bold tracking-tight text-foreground">{{ title }}</h2>
                </div>
                <button
                    type="button"
                    class="rounded-full p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    @click="emit('close')"
                >
                    <X class="size-5" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                <div
                    v-if="error"
                    class="mb-3 rounded-xl border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-300"
                >
                    {{ error }}
                </div>
                <div
                    v-if="success"
                    class="mb-3 rounded-xl border border-green-500/40 bg-green-500/10 px-3 py-2 text-sm text-green-600 dark:text-green-300"
                >
                    {{ success }}
                </div>

                <!-- Guest: login / register -->
                <template v-if="!user">
                    <div class="mb-4 grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
                        <button
                            type="button"
                            class="rounded-lg py-2 text-sm font-semibold transition"
                            :class="
                                mode === 'login'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="switchMode('login')"
                        >
                            Log in
                        </button>
                        <button
                            type="button"
                            class="rounded-lg py-2 text-sm font-semibold transition"
                            :class="
                                mode === 'register'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="switchMode('register')"
                        >
                            Register
                        </button>
                    </div>

                    <SocialAuthButtons
                        class="mb-4"
                        :hint="mode === 'login' ? 'Or use email' : 'Or register with email'"
                    />

                    <!-- Login -->
                    <form v-if="mode === 'login'" class="space-y-3" @submit="handleLogin">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Email</label>
                            <input
                                ref="loginEmailInput"
                                v-model="loginEmail"
                                type="email"
                                required
                                autocomplete="email"
                                :class="inputClass"
                                placeholder="you@email.com"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Password</label>
                            <div class="relative">
                                <input
                                    v-model="loginPassword"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    :class="inputClass"
                                    class="pr-11"
                                    placeholder="Your password"
                                />
                                <button
                                    type="button"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </button>
                            </div>
                        </div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="mt-1 h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white transition hover:bg-orange-600 disabled:opacity-50"
                        >
                            {{ loading ? 'Logging in…' : 'Log in' }}
                        </button>
                    </form>

                    <!-- Register steps -->
                    <form v-else class="space-y-3" @submit="handleRegister">
                        <div class="mb-1 flex items-center gap-2">
                            <span
                                class="flex size-6 items-center justify-center rounded-full text-[11px] font-bold"
                                :class="
                                    registerStep === 1
                                        ? 'bg-orange-500 text-white'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >1</span
                            >
                            <div class="h-0.5 flex-1 rounded bg-border" :class="registerStep === 2 && 'bg-orange-500/50'" />
                            <span
                                class="flex size-6 items-center justify-center rounded-full text-[11px] font-bold"
                                :class="
                                    registerStep === 2
                                        ? 'bg-orange-500 text-white'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >2</span
                            >
                        </div>

                        <template v-if="registerStep === 1">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-muted-foreground">Name</label>
                                <input
                                    v-model="registerName"
                                    type="text"
                                    required
                                    autocomplete="name"
                                    :class="fieldErrors.name ? inputErrorClass : inputClass"
                                    placeholder="Your name"
                                    @input="clearFieldError('name')"
                                />
                                <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-500">{{ fieldErrors.name }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-muted-foreground">Email</label>
                                <input
                                    v-model="registerEmail"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    :class="fieldErrors.email ? inputErrorClass : inputClass"
                                    placeholder="you@email.com"
                                    @input="clearFieldError('email')"
                                />
                                <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-500">{{ fieldErrors.email }}</p>
                            </div>
                            <button
                                type="submit"
                                class="mt-1 h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white transition hover:bg-orange-600"
                            >
                                Continue
                            </button>
                        </template>

                        <template v-else>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-muted-foreground">Password</label>
                                <div class="relative">
                                    <input
                                        v-model="registerPassword"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        minlength="6"
                                        autocomplete="new-password"
                                        :class="fieldErrors.password ? inputErrorClass : inputClass"
                                        class="pr-11"
                                        placeholder="At least 6 characters"
                                        @input="clearFieldError('password')"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground"
                                        @click="showPassword = !showPassword"
                                    >
                                        <EyeOff v-if="showPassword" class="size-4" />
                                        <Eye v-else class="size-4" />
                                    </button>
                                </div>
                                <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-500">
                                    {{ fieldErrors.password }}
                                </p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-muted-foreground">
                                    Phone <span class="text-muted-foreground/70">(optional)</span>
                                </label>
                                <input
                                    v-model="registerPhone"
                                    type="tel"
                                    maxlength="15"
                                    autocomplete="tel"
                                    :class="inputClass"
                                    placeholder="For delivery updates"
                                />
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-1">
                                    <label class="mb-1 block text-xs font-medium text-muted-foreground">
                                        Area code
                                    </label>
                                    <input
                                        v-model="registerAreaCode"
                                        type="text"
                                        maxlength="10"
                                        autocomplete="postal-code"
                                        :class="inputClass"
                                        placeholder="V94"
                                    />
                                </div>
                                <div class="col-span-2">
                                    <label class="mb-1 block text-xs font-medium text-muted-foreground">
                                        Address
                                    </label>
                                    <input
                                        v-model="registerAddress"
                                        type="text"
                                        autocomplete="street-address"
                                        :class="inputClass"
                                        placeholder="Street, house / apartment"
                                    />
                                </div>
                            </div>
                            <button
                                type="submit"
                                :disabled="loading"
                                class="mt-1 h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white transition hover:bg-orange-600 disabled:opacity-50"
                            >
                                {{ loading ? 'Creating…' : 'Create account' }}
                            </button>
                        </template>
                    </form>
                </template>

                <!-- Logged in -->
                <template v-else>
                    <!-- Menu -->
                    <template v-if="panel === 'menu'">
                        <div class="mb-4 flex items-center gap-3 rounded-2xl border border-border bg-muted/40 p-3">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-full bg-orange-500 text-lg font-bold text-white"
                            >
                                {{ user.name?.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-foreground">{{ user.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-muted"
                                @click="panel = 'profile'"
                            >
                                <span class="flex size-9 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500">
                                    <UserRound class="size-4" />
                                </span>
                                <span class="flex-1 text-sm font-medium text-foreground">My account</span>
                                <ChevronRight class="size-4 text-muted-foreground" />
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-muted"
                                @click="panel = 'password'"
                            >
                                <span class="flex size-9 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500">
                                    <KeyRound class="size-4" />
                                </span>
                                <span class="flex-1 text-sm font-medium text-foreground">Change password</span>
                                <ChevronRight class="size-4 text-muted-foreground" />
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-muted"
                                @click="handleViewOrders"
                            >
                                <span class="flex size-9 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500">
                                    <ShoppingBag class="size-4" />
                                </span>
                                <span class="flex-1 text-sm font-medium text-foreground">My orders</span>
                                <ChevronRight class="size-4 text-muted-foreground" />
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-muted"
                                @click="handleBillingPortal"
                            >
                                <span class="flex size-9 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500">
                                    <CreditCard class="size-4" />
                                </span>
                                <span class="flex-1 text-sm font-medium text-foreground">Billing</span>
                                <ChevronRight class="size-4 text-muted-foreground" />
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-muted"
                                @click="handleSupport"
                            >
                                <span class="flex size-9 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500">
                                    <Headset class="size-4" />
                                </span>
                                <span class="flex-1 text-sm font-medium text-foreground">Help & support</span>
                                <ChevronRight class="size-4 text-muted-foreground" />
                            </button>
                        </div>

                        <div class="mt-3">
                            <ThemeToggle />
                        </div>

                        <button
                            type="button"
                            :disabled="loading"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-border py-2.5 text-sm font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:opacity-50"
                            @click="handleLogout"
                        >
                            <LogOut class="size-4" />
                            {{ loading ? 'Logging out…' : 'Log out' }}
                        </button>
                    </template>

                    <!-- Edit profile -->
                    <form v-else-if="panel === 'profile'" class="space-y-3" @submit="handleUpdateProfile">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Name</label>
                            <input v-model="editName" type="text" required :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Email</label>
                            <input v-model="editEmail" type="email" required :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Phone</label>
                            <input v-model="editPhone" type="tel" :class="inputClass" />
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-1">
                                <label class="mb-1 block text-xs font-medium text-muted-foreground">Area code</label>
                                <input
                                    v-model="editAreaCode"
                                    type="text"
                                    maxlength="10"
                                    autocomplete="postal-code"
                                    :class="inputClass"
                                    placeholder="V94"
                                />
                            </div>
                            <div class="col-span-2">
                                <label class="mb-1 block text-xs font-medium text-muted-foreground">Address</label>
                                <input
                                    v-model="editAddress"
                                    type="text"
                                    autocomplete="street-address"
                                    :class="inputClass"
                                    placeholder="Street, house / apartment"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Delivery location</label>
                            <LocationPicker
                                :latitude="editLatitude"
                                :longitude="editLongitude"
                                height="130px"
                                :on-location-change="
                                    (lat, lng) => {
                                        editLatitude = lat;
                                        editLongitude = lng;
                                    }
                                "
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white transition hover:bg-orange-600 disabled:opacity-50"
                        >
                            {{ loading ? 'Saving…' : 'Save changes' }}
                        </button>
                    </form>

                    <!-- Change password -->
                    <form v-else class="space-y-3" @submit="handleChangePassword">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">New password</label>
                            <div class="relative">
                                <input
                                    v-model="newPassword"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    minlength="6"
                                    autocomplete="new-password"
                                    :class="fieldErrors.password ? inputErrorClass : inputClass"
                                    class="pr-11"
                                    placeholder="At least 6 characters"
                                    @input="clearFieldError('password')"
                                />
                                <button
                                    type="button"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Confirm password</label>
                            <input
                                v-model="confirmPassword"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                minlength="6"
                                autocomplete="new-password"
                                :class="inputClass"
                                placeholder="Repeat password"
                            />
                            <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-500">
                                {{ fieldErrors.password }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="h-11 w-full rounded-xl bg-orange-500 text-sm font-bold text-white transition hover:bg-orange-600 disabled:opacity-50"
                        >
                            {{ loading ? 'Updating…' : 'Update password' }}
                        </button>
                    </form>
                </template>
            </div>
        </div>
    </div>
</template>
