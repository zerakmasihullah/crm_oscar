<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MisForm, MisPage } from '@/components/mis';
import PromotionCampaignForm from '@/components/promotions/PromotionCampaignForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

defineProps<{
    templates: Array<{ value: string; label: string; hint?: string }>;
    audience: { users: number; newsletter: number; both: number };
}>();

const { t } = useTranslations();

const form = useForm({
    subject: '',
    content: '',
    image: null as File | null,
    remove_image: false,
    template: 'classic-banner',
    recipient_type: 'both',
    status: 'draft',
    scheduled_at: '',
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            scheduled_at: data.status === 'scheduled' ? data.scheduled_at : null,
        }))
        .post('/promotions', { forceFormData: true });
}

defineOptions(resourcePageBreadcrumbs('Promotions', '/promotions', 'create'));
</script>

<template>
    <Head :title="t('New campaign')" />

    <MisPage>
        <MisForm
            cancel-href="/promotions"
            :processing="form.processing"
            :errors="form.errors"
            :submit-label="t('Save')"
            max-width="full"
            @submit="submit"
        >
            <PromotionCampaignForm
                :form="form"
                :templates="templates"
                :audience="audience"
                @update:subject="(v) => (form.subject = v)"
                @update:content="(v) => (form.content = v)"
                @update:template="(v) => (form.template = v)"
                @update:recipient_type="(v) => (form.recipient_type = v)"
                @update:status="(v) => (form.status = v)"
                @update:scheduled_at="(v) => (form.scheduled_at = v)"
                @update:image="(v) => (form.image = v)"
                @update:remove_image="(v) => (form.remove_image = v)"
            />
        </MisForm>
    </MisPage>
</template>
