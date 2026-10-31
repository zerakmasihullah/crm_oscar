<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { UtensilsCrossed } from '@lucide/vue';
import FileUploadField from '@/components/FileUploadField.vue';
import {
    MisForm,
    MisInputField,
    MisSection,
    MisTextareaField,
} from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = useForm({
    title: '',
    description: '',
    image: null as File | null,
});

function onImageDeferred(files: File[]): void {
    form.image = files[0] ?? null;
}

function onImageRemoved(): void {
    form.image = null;
}

function submit(): void {
    form.post('/menus', { forceFormData: true });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Menus', href: '/menus' },
            { title: 'Create', href: '/menus/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Create menu')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MisForm
                :processing="form.processing"
                :errors="form.errors"
                show-error-summary
                cancel-href="/menus"
                :submit-label="t('Save menu')"
                max-width="full"
                @submit="submit"
            >
                <MisSection
                    :title="t('Create menu')"
                    :description="t('Category shown on the storefront menu.')"
                    :icon="UtensilsCrossed"
                >
                    <div class="grid gap-6 lg:grid-cols-[12rem_minmax(0,1fr)]">
                        <FileUploadField
                            mode="deferred"
                            accept="image/*"
                            preview="image"
                            :label="t('Image')"
                            :hint="t('PNG or JPG up to 5MB.')"
                            :max-bytes="5 * 1024 * 1024"
                            :error="form.errors.image"
                            @update:deferred="onImageDeferred"
                            @remove="onImageRemoved"
                        />

                        <div class="grid content-start gap-5">
                            <MisInputField
                                id="title"
                                v-model="form.title"
                                :label="t('Title')"
                                :placeholder="t('e.g. Burgers')"
                                :error="form.errors.title"
                                required
                            />
                            <MisTextareaField
                                id="description"
                                v-model="form.description"
                                :label="t('Description')"
                                :placeholder="t('Short description for this menu category.')"
                                :error="form.errors.description"
                                :rows="5"
                            />
                        </div>
                    </div>
                </MisSection>
            </MisForm>
        </SettingsPanel>
    </SettingsPage>
</template>
