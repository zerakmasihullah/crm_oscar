<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    MisForm,
    MisInputField,
    MisPage,
    MisSelectField,
} from '@/components/mis';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

const { t } = useTranslations();

const days = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
] as const;

const form = useForm({
    name: '',
    discount_type: 'percentage',
    discount_value: '' as string | number,
    minimum_purchase_amount: '' as string | number,
    days_of_week: [] as string[],
    is_active: true,
});

function toggleDay(day: string, checked: boolean | 'indeterminate') {
    if (checked === true) {
        if (!form.days_of_week.includes(day)) {
            form.days_of_week = [...form.days_of_week, day];
        }
        return;
    }
    form.days_of_week = form.days_of_week.filter((d) => d !== day);
}

function submit() {
    form.transform((data) => ({
        ...data,
        discount_value: data.discount_value === '' ? null : data.discount_value,
        minimum_purchase_amount:
            data.minimum_purchase_amount === ''
                ? null
                : data.minimum_purchase_amount,
    })).post('/discounts');
}

defineOptions(resourcePageBreadcrumbs('Discounts', '/discounts', 'create'));
</script>

<template>
    <Head :title="t('Create discount')" />

    <MisPage narrow>
        <MisForm
            :title="t('Create discount')"
            cancel-href="/discounts"
            :processing="form.processing"
            :errors="form.errors"
            max-width="lg"
            @submit="submit"
        >
            <MisInputField
                id="name"
                v-model="form.name"
                :label="t('Discount name')"
                :error="form.errors.name"
                required
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <MisSelectField
                    id="discount_type"
                    v-model="form.discount_type"
                    :label="t('Discount type')"
                    :error="form.errors.discount_type"
                    :options="[
                        { value: 'percentage', label: t('Percentage (%)') },
                        { value: 'fixed', label: t('Fixed amount') },
                    ]"
                    required
                />
                <MisInputField
                    id="discount_value"
                    v-model="form.discount_value"
                    type="number"
                    step="0.01"
                    min="0"
                    :label="
                        form.discount_type === 'percentage'
                            ? t('Discount percentage (%)')
                            : t('Discount amount')
                    "
                    :error="form.errors.discount_value"
                    required
                />
                <MisInputField
                    id="minimum_purchase_amount"
                    v-model="form.minimum_purchase_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    :label="t('Minimum purchase amount')"
                    :hint="t('Leave empty to apply to all orders.')"
                    :error="form.errors.minimum_purchase_amount"
                />
            </div>

            <div class="space-y-2">
                <Label>{{ t('Days of week') }}</Label>
                <p class="text-xs text-muted-foreground">
                    {{ t('Leave empty to apply all days.') }}
                </p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <label
                        v-for="day in days"
                        :key="day"
                        class="flex items-center gap-2 text-sm capitalize"
                    >
                        <Checkbox
                            :model-value="form.days_of_week.includes(day)"
                            @update:model-value="(v) => toggleDay(day, v as boolean)"
                        />
                        {{ day }}
                    </label>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    :model-value="form.is_active"
                    @update:model-value="(v) => (form.is_active = v === true)"
                />
                {{ t('Active') }}
            </label>
        </MisForm>
    </MisPage>
</template>
