<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { MisAlert, MisSection } from '@/components/mis';
import PlatformSettingsTabs from '@/components/settings/PlatformSettingsTabs.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import SettingsSwitch from '@/components/settings/SettingsSwitch.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

type GoogleAuthSettings = {
    enabled: boolean;
    client_id: string;
    client_secret_set: boolean;
    redirect_uri: string;
    credentials_configured: boolean;
    active: boolean;
    env_default: boolean;
};

const props = defineProps<{
    settings: GoogleAuthSettings;
}>();

const { t } = useTranslations();

const form = useForm({
    enabled: props.settings.enabled,
    client_id: props.settings.client_id,
    client_secret: '',
    redirect_uri: props.settings.redirect_uri,
});

watch(
    () => props.settings,
    (settings) => {
        form.defaults({
            enabled: settings.enabled,
            client_id: settings.client_id,
            client_secret: '',
            redirect_uri: settings.redirect_uri,
        });
        form.reset();
    },
);

const credentialsReady = computed(
    () =>
        form.client_id.trim() !== '' &&
        (props.settings.client_secret_set || form.client_secret.trim() !== ''),
);

function submit(): void {
    form.put('/settings/google-auth', { preserveScroll: true });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/system-control' },
            { title: 'Google Auth', href: '/settings/google-auth' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Google Auth')" />

    <SettingsPage data-tour="settings-google-auth">
        <template #tabs>
            <PlatformSettingsTabs />
        </template>

        <MisAlert v-if="form.enabled && !credentialsReady" tone="warning">
            {{
                t(
                    'Google Auth is turned on, but client ID and secret are still required.',
                )
            }}
        </MisAlert>

        <MisAlert
            v-else-if="form.enabled && credentialsReady"
            tone="success"
        >
            {{
                t(
                    'Google sign-in is active on the login and register pages.',
                )
            }}
        </MisAlert>

        <SettingsPanel>
            <MisSection
                :title="t('Google Auth')"
                :icon="KeyRound"
            >
                <template #actions>
                    <SettingsSwitch
                        :model-value="form.enabled"
                        @update:model-value="(value) => (form.enabled = value)"
                    />
                </template>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="client_id">{{ t('Client ID') }}</Label>
                            <Input
                                id="client_id"
                                v-model="form.client_id"
                                type="text"
                                autocomplete="off"
                                :placeholder="t('Google OAuth client ID')"
                            />
                            <InputError :message="form.errors.client_id" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <div class="flex items-center justify-between gap-2">
                                <Label for="client_secret">{{
                                    t('Client secret')
                                }}</Label>
                                <Badge
                                    v-if="settings.client_secret_set"
                                    variant="secondary"
                                >
                                    {{ t('Configured') }}
                                </Badge>
                            </div>
                            <Input
                                id="client_secret"
                                v-model="form.client_secret"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    settings.client_secret_set
                                        ? t(
                                              'Leave blank to keep current secret',
                                          )
                                        : t('Google OAuth client secret')
                                "
                            />
                            <InputError :message="form.errors.client_secret" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="redirect_uri">{{
                                t('Redirect URI')
                            }}</Label>
                            <Input
                                id="redirect_uri"
                                v-model="form.redirect_uri"
                                type="url"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.redirect_uri" />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between gap-3 rounded-xl border border-border/70 px-3.5 py-3"
                    >
                        <div>
                            <div class="text-sm font-medium">
                                {{ t('Env default') }}
                            </div>
                        </div>
                        <Badge variant="secondary">
                            {{ settings.env_default ? t('On') : t('Off') }}
                        </Badge>
                    </div>

                    <div class="flex justify-end border-t border-border/50 pt-4">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.isDirty"
                        >
                            {{ t('Save') }}
                        </Button>
                    </div>
                </form>
            </MisSection>
        </SettingsPanel>
    </SettingsPage>
</template>
