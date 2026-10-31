<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Store } from '@lucide/vue';
import { MisForm, MisInputField, MisSection } from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import SettingsToggleRow from '@/components/settings/SettingsToggleRow.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    item: {
        id: number;
        name: string;
        is_active: boolean;
    };
}>();

const { t } = useTranslations();

const form = useForm({
    name: props.item.name,
    is_active: props.item.is_active,
});

function submit() {
    form.put(`/settings/platforms/${props.item.id}`);
}
</script>

<template>
    <Head :title="t('Edit sales platform')" />

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
                :submit-label="t('Save changes')"
                max-width="full"
                @submit="submit"
            >
                <MisSection
                    :title="t('Edit sales platform')"
                    :description="t('Update this sales channel.')"
                    :icon="Store"
                >
                    <div class="grid gap-5">
                        <MisInputField
                            id="name"
                            v-model="form.name"
                            :label="t('Name')"
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
