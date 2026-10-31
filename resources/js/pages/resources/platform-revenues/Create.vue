<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    MisAlert,
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
    platforms: PlatformOption[];
    defaults?: { revenue_date?: string };
}>();

const { t } = useTranslations();

const form = useForm({
    platform_id: props.platforms[0]?.value
        ? String(props.platforms[0].value)
        : '',
    amount: '' as string | number,
    revenue_date:
        props.defaults?.revenue_date ?? new Date().toISOString().slice(0, 10),
    notes: '',
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
        .post('/platform-revenues');
}

defineOptions(
    resourcePageBreadcrumbs('Platform revenue', '/platform-revenues', 'create'),
);
</script>

<template>
    <Head :title="t('Add platform revenue')" />

    <MisPage narrow>
        <MisForm
            :processing="form.processing"
            :errors="form.errors"
            show-error-summary
            cancel-href="/platform-revenues"
            :submit-label="t('Save revenue')"
            max-width="lg"
            @submit="submit"
        >
            <div class="grid gap-5">
                <MisAlert
                    v-if="platforms.length === 0"
                    tone="warning"
                >
                    {{ t('Create a platform first.') }}
                    <Link
                        href="/settings/platforms/create"
                        class="ms-1 font-medium underline"
                    >
                        {{ t('Add platform') }}
                    </Link>
                </MisAlert>

                <MisSelectField
                    id="platform_id"
                    v-model="form.platform_id"
                    :label="t('Platform')"
                    :error="form.errors.platform_id"
                    :options="platformOptions"
                    :placeholder="t('Select platform')"
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
                    :placeholder="t('Optional note…')"
                    :error="form.errors.notes"
                    :rows="3"
                />
            </div>
        </MisForm>
    </MisPage>
</template>
