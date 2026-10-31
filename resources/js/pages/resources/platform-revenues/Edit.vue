<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    MisForm,
    MisInputField,
    MisPage,
    MisSelectField,
    MisTextareaField,
} from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type PlatformOption = { value: number | string; label: string };

const props = defineProps<{
    item: {
        id: number;
        platform_id: number;
        amount: string | number;
        revenue_date: string;
        notes: string | null;
    };
    platforms: PlatformOption[];
}>();

const { t } = useTranslations();

const form = useForm({
    platform_id: String(props.item.platform_id),
    amount: props.item.amount,
    revenue_date: props.item.revenue_date,
    notes: props.item.notes ?? '',
});

const platformOptions = props.platforms.map((p) => ({
    value: String(p.value),
    label: p.label,
}));

function submit() {
    form
        .transform((data) => ({
            ...data,
            platform_id: Number(data.platform_id),
            amount: data.amount === '' ? null : data.amount,
            notes: data.notes.trim() === '' ? null : data.notes,
        }))
        .put(`/platform-revenues/${props.item.id}`);
}

defineOptions(
    resourcePageBreadcrumbs('Platform revenue', '/platform-revenues', 'edit'),
);
</script>

<template>
    <Head :title="t('Edit platform revenue')" />

    <MisPage narrow>
        <MisForm
            :processing="form.processing"
            :errors="form.errors"
            show-error-summary
            cancel-href="/platform-revenues"
            :submit-label="t('Save changes')"
            max-width="lg"
            @submit="submit"
        >
            <div class="grid gap-5">
                <MisSelectField
                    id="platform_id"
                    v-model="form.platform_id"
                    :label="t('Platform')"
                    :error="form.errors.platform_id"
                    :options="platformOptions"
                    required
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <MisInputField
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        prefix="€"
                        step="0.01"
                        min="0.01"
                        :label="t('Revenue amount')"
                        :error="form.errors.amount"
                        required
                    />
                    <MisInputField
                        id="revenue_date"
                        v-model="form.revenue_date"
                        type="date"
                        :label="t('Date')"
                        :error="form.errors.revenue_date"
                        required
                    />
                </div>

                <MisTextareaField
                    id="notes"
                    v-model="form.notes"
                    :label="t('Notes')"
                    :error="form.errors.notes"
                    :rows="3"
                />
            </div>
        </MisForm>
    </MisPage>
</template>
