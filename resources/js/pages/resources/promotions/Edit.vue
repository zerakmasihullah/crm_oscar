<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MisForm, MisPage } from '@/components/mis';
import PromotionCampaignForm from '@/components/promotions/PromotionCampaignForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

const props = defineProps<{
    item: {
        id: number;
        subject: string;
        content: string;
        image_url: string | null;
        template: string;
        recipient_type: string;
        status: string;
        scheduled_at: string | null;
    };
    templates: Array<{ value: string; label: string; hint?: string }>;
    audience: { users: number; newsletter: number; both: number };
}>();

const { t } = useTranslations();

function toLocalInput(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const form = useForm({
    subject: props.item.subject,
    content: props.item.content,
    image: null as File | null,
    remove_image: false,
    template: props.item.template,
    recipient_type: props.item.recipient_type,
    status: props.item.status === 'sent' ? 'sent' : props.item.status,
    scheduled_at: toLocalInput(props.item.scheduled_at),
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            scheduled_at: data.status === 'scheduled' ? data.scheduled_at : null,
        }))
        .put(`/promotions/${props.item.id}`, { forceFormData: true });
}

defineOptions(resourcePageBreadcrumbs('Promotions', '/promotions', 'edit'));
</script>

<template>
    <Head :title="t('Edit campaign')" />

    <MisPage>
        <MisForm
            :cancel-href="`/promotions/${item.id}`"
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
                :existing-image-url="item.image_url"
                :sent-locked="item.status === 'sent'"
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
