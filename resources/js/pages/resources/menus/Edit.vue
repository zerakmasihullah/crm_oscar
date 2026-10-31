<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { UtensilsCrossed } from '@lucide/vue';
import { computed } from 'vue';
import FileUploadField, {
    type ExistingUploadFile,
} from '@/components/FileUploadField.vue';
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

const props = defineProps<{
    item: {
        id: number;
        title: string;
        description?: string | null;
        image?: string | null;
        image_url?: string | null;
    };
}>();

const { t } = useTranslations();

const form = useForm({
    title: props.item.title ?? '',
    description: props.item.description ?? '',
    image: null as File | null,
    remove_image: false,
});

const existingImage = computed<ExistingUploadFile | null>(() => {
    if (!props.item.image_url && !props.item.image) return null;
    return {
        name: props.item.image?.split('/').pop() || t('Image'),
        url: props.item.image_url ?? undefined,
        path: props.item.image ?? undefined,
    };
});

function onImageDeferred(files: File[]): void {
    form.image = files[0] ?? null;
    form.remove_image = false;
}

function onImageRemoved(): void {
    form.image = null;
    form.remove_image = true;
}

function submit(): void {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
            remove_image: data.remove_image ? 1 : 0,
        }))
        .post(`/menus/${props.item.id}`, {
            forceFormData: true,
            onFinish: () => form.transform((data) => data),
        });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Menus', href: '/menus' },
            { title: 'Edit', href: '/menus' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Edit menu')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MisForm
                :processing="form.processing"
                :errors="form.errors"
                show-error-summary
                :cancel-href="`/menus/${item.id}`"
                :submit-label="t('Save changes')"
                max-width="full"
                @submit="submit"
            >
                <MisSection
                    :title="t('Edit menu')"
                    :description="t('Update this storefront menu category.')"
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
                            :existing="existingImage"
                            :error="form.errors.image"
                            @update:deferred="onImageDeferred"
                            @remove="onImageRemoved"
                        />

                        <div class="grid content-start gap-5">
                            <MisInputField
                                id="title"
                                v-model="form.title"
                                :label="t('Title')"
                                :error="form.errors.title"
                                required
                            />
                            <MisTextareaField
                                id="description"
                                v-model="form.description"
                                :label="t('Description')"
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
