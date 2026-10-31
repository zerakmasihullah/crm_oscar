<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Store } from '@lucide/vue';
import { MisForm, MisInputField, MisSection } from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import SettingsToggleRow from '@/components/settings/SettingsToggleRow.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = useForm({
    name: '',
    is_active: true,
});

function submit() {
    form.post('/settings/platforms');
}
</script>

<template>
    <Head :title="t('Add sales platform')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MisForm
                :processing="form.processing"
                :errors="form.errors"
                show-error-summary
                cancel-href="/settings/platforms"
                :submit-label="t('Save platform')"
                max-width="full"
                @submit="submit"
            >
                <MisSection
                    :title="t('Add sales platform')"
                    :description="t('Channels where you sell outside this app.')"
                    :icon="Store"
                >
                    <div class="grid gap-5">
                        <MisInputField
                            id="name"
                            v-model="form.name"
                            :label="t('Name')"
                            :placeholder="t('e.g. Uber Eats, Deliveroo')"
                            :error="form.errors.name"
                            required
                        />

                        <div class="rounded-xl border border-border/70 px-4">
                            <SettingsToggleRow
                                :title="t('Active')"
                                :description="t('Inactive platforms cannot receive new revenue entries.')"
                                :model-value="form.is_active"
                                @update:model-value="(value) => (form.is_active = value)"
                            />
                        </div>
                    </div>
                </MisSection>
            </MisForm>
        </SettingsPanel>
    </SettingsPage>
</template>
