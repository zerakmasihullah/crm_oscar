<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    KeyRound,
    Languages,
    MonitorSmartphone,
    ShieldAlert,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import { MisSection, MisTabs } from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import type { MisTabItem } from '@/components/mis';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { LocaleCode } from '@/types/locale';

type BrowserSession = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
    is_current: boolean;
};

type ProfileTab = 'account' | 'security' | 'sessions' | 'danger';

type Props = {
    mustVerifyEmail?: boolean;
    status?: string | null;
    passwordRules: string;
    sessions?: BrowserSession[];
    canListSessions?: boolean;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = withDefaults(defineProps<Props>(), {
    mustVerifyEmail: false,
    status: null,
    sessions: () => [],
    canListSessions: false,
});

const page = usePage();
const { t } = useTranslations();
const { locale, locales, updateLocale } = useLocale();
const user = computed(() => page.props.auth.user);
const multilingual = computed(
    () => page.props.kitFeatures?.multilingual !== false,
);

const initials = computed(() => {
    const name = String(user.value?.name ?? '');
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (!parts.length) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
});

function resolveTab(section: string | null): ProfileTab {
    switch (section) {
        case 'security':
        case 'password':
        case 'two-factor':
        case 'passkeys':
            return 'security';
        case 'sessions':
            return 'sessions';
        case 'danger':
            return 'danger';
        case 'preferences':
        case 'profile':
        case 'account':
        default:
            return 'account';
    }
}

function sectionFromUrl(url: string): string | null {
    const query = url.includes('?') ? (url.split('?')[1] ?? '') : '';

    return new URLSearchParams(query).get('section');
}

const activeTab = ref<ProfileTab>(
    resolveTab(
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search).get('section')
            : sectionFromUrl(page.url),
    ),
);

watch(
    () => page.url,
    (url) => {
        activeTab.value = resolveTab(sectionFromUrl(url));
    },
);

watch(activeTab, (tab) => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    const current = url.searchParams.get('section');
    const next = tab === 'account' ? null : tab;

    if ((current ?? null) === next) {
        return;
    }

    if (next) {
        url.searchParams.set('section', next);
    } else {
        url.searchParams.delete('section');
    }

    window.history.replaceState({}, '', `${url.pathname}${url.search}`);
});

const tabs = computed<MisTabItem[]>(() => [
    { id: 'account', label: t('Account'), icon: UserRound },
    { id: 'security', label: t('Security'), icon: ShieldCheck },
    { id: 'sessions', label: t('Sessions'), icon: MonitorSmartphone },
    { id: 'danger', label: t('Danger zone'), icon: ShieldAlert },
]);

function revokeOtherSessions(): void {
    router.post('/settings/security/sessions/revoke-others');
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('Profile settings')" />

    <h1 class="sr-only">{{ t('Profile settings') }}</h1>

    <SettingsPage>
        <div
            class="flex flex-wrap items-center gap-3 rounded-2xl border border-border/70 bg-card/80 px-4 py-3.5 shadow-soft"
        >
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white"
                aria-hidden="true"
            >
                {{ initials }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold tracking-tight">
                    {{ user.name }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ user.email }}
                </p>
            </div>
        </div>

        <MisTabs v-model="activeTab" :tabs="tabs" />

        <SettingsPanel v-show="activeTab === 'account'">
            <MisSection
                :title="t('Profile')"
            >
                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">{{ t('Name') }}</Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="user.name"
                                required
                                autocomplete="name"
                                :placeholder="t('Full name')"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">{{ t('Email address') }}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                :default-value="user.email"
                                required
                                autocomplete="username"
                                :placeholder="t('Email address')"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <div
                        v-if="mustVerifyEmail && !user.email_verified_at"
                        class="rounded-xl border border-amber-200/80 bg-amber-50/80 p-4 text-sm text-amber-950 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-50"
                    >
                        <p>
                            {{ t('Your email address is unverified.') }}
                            <Link
                                :href="send()"
                                as="button"
                                class="font-medium underline underline-offset-4"
                            >
                                {{
                                    t(
                                        'Click here to re-send the verification email.',
                                    )
                                }}
                            </Link>
                        </p>
                        <p
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 font-medium text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] dark:text-[var(--brand-accent)]"
                        >
                            {{
                                t(
                                    'A new verification link has been sent to your email address.',
                                )
                            }}
                        </p>
                    </div>

                    <div class="flex justify-end border-t border-border/50 pt-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                        >
                            {{ t('Save') }}
                        </Button>
                    </div>
                </Form>
            </MisSection>

            <div
                v-if="multilingual"
                class="border-t border-border/50 pt-5"
            >
                <MisSection
                    :title="t('Language')"
                    :icon="Languages"
                >
                    <select
                        id="profile-language"
                        class="flex h-10 w-full max-w-sm rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="locale"
                        @change="
                            updateLocale(
                                ($event.target as HTMLSelectElement)
                                    .value as LocaleCode,
                            )
                        "
                    >
                        <option
                            v-for="item in locales"
                            :key="item.code"
                            :value="item.code"
                        >
                            {{ item.native }} ({{ item.name }})
                        </option>
                    </select>
                </MisSection>
            </div>
        </SettingsPanel>

        <div v-show="activeTab === 'security'" class="space-y-5">
            <SettingsPanel>
                <MisSection
                    :title="t('Change password')"
                    :icon="KeyRound"
                >
                    <Form
                        v-bind="SecurityController.update.form()"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        :reset-on-error="[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2 max-w-md">
                            <Label for="current_password">{{
                                t('Current password')
                            }}</Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                                :placeholder="t('Current password')"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="password">{{
                                    t('New password')
                                }}</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    autocomplete="new-password"
                                    :placeholder="t('New password')"
                                    :passwordrules="props.passwordRules"
                                />
                                <InputError :message="errors.password" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="password_confirmation">{{
                                    t('Confirm password')
                                }}</Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    :placeholder="t('Confirm password')"
                                    :passwordrules="props.passwordRules"
                                />
                                <InputError
                                    :message="errors.password_confirmation"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-border/50 pt-4">
                            <Button
                                :disabled="processing"
                                data-test="update-password-button"
                            >
                                {{ t('Update password') }}
                            </Button>
                        </div>
                    </Form>
                </MisSection>
            </SettingsPanel>

            <SettingsPanel v-if="canManageTwoFactor">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[17px] font-semibold tracking-tight">
                            {{ t('Two-factor authentication') }}
                        </h2>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            twoFactorEnabled
                                ? 'bg-[var(--brand-accent)]/10 text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] dark:text-[var(--brand-accent)]'
                                : 'bg-amber-500/10 text-amber-800 dark:text-amber-200'
                        "
                    >
                        <CheckCircle2
                            v-if="twoFactorEnabled"
                            class="size-3.5"
                        />
                        <ShieldAlert v-else class="size-3.5" />
                        {{
                            twoFactorEnabled
                                ? t('Enabled')
                                : t('Not enabled')
                        }}
                    </span>
                </div>
                <ManageTwoFactor
                    embedded
                    :can-manage-two-factor="canManageTwoFactor"
                    :requires-confirmation="requiresConfirmation"
                    :two-factor-enabled="twoFactorEnabled"
                />
            </SettingsPanel>

            <SettingsPanel v-if="canManagePasskeys">
                <MisSection
                    :title="t('Passkeys')"
                >
                    <ManagePasskeys
                        embedded
                        :can-manage-passkeys="canManagePasskeys"
                        :passkeys="passkeys"
                    />
                </MisSection>
            </SettingsPanel>
        </div>

        <SettingsPanel v-show="activeTab === 'sessions'">
            <MisSection
                :title="t('Browser sessions')"
            >
                <div
                    v-if="canListSessions && sessions.length"
                    class="overflow-hidden rounded-xl border border-border/70"
                >
                    <div
                        v-for="session in sessions"
                        :key="session.id"
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 px-3.5 py-3 text-sm last:border-b-0"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ session.ip_address || '—' }}
                                <span
                                    v-if="session.is_current"
                                    class="ms-1 text-xs font-normal text-muted-foreground"
                                >
                                    ({{ t('This device') }})
                                </span>
                            </p>
                            <p class="truncate text-muted-foreground">
                                {{ session.user_agent || '—' }}
                            </p>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                new Date(
                                    session.last_activity * 1000,
                                ).toLocaleString()
                            }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Revoke other sessions to invalidate older logins on other devices.',
                        )
                    }}
                </p>

                <div class="pt-2">
                    <Button variant="outline" @click="revokeOtherSessions">
                        {{ t('Sign out other sessions') }}
                    </Button>
                </div>
            </MisSection>
        </SettingsPanel>

        <SettingsPanel
            v-show="activeTab === 'danger'"
            class="border-red-200/80 dark:border-red-500/20"
        >
            <MisSection
                :title="t('Delete account')"
            >
                <DeleteUser embedded />
            </MisSection>
        </SettingsPanel>
    </SettingsPage>
</template>
