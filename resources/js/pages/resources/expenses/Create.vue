<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import {
    MisForm,
    MisInputField,
    MisPage,
    MisSelectField,
    MisTextareaField,
} from '@/components/mis';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type CategoryOption = { value: number | string; label: string };

const props = defineProps<{
    categories: CategoryOption[];
    defaults?: { expense_date?: string; period_end?: string | null };
}>();

const { t } = useTranslations();

const categoryList = ref(
    props.categories.map((c) => ({
        value: String(c.value),
        label: c.label,
    })),
);
const showNewCategory = ref(false);
const newCategoryName = ref('');
const addingCategory = ref(false);
const categoryError = ref('');
const newCategoryInput = ref<HTMLInputElement | null>(null);

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    expense_category_id: categoryList.value[0]?.value ?? '',
    amount: '' as string | number,
    expense_date: props.defaults?.expense_date ?? today,
    period_end: props.defaults?.period_end ?? (props.defaults?.expense_date ?? today),
    vendor: '',
    notes: '',
});

const periodDays = computed(() => {
    const from = form.expense_date;
    const to = form.period_end || form.expense_date;
    if (!from || !to) return 1;
    const start = new Date(from);
    const end = new Date(to);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) {
        return 1;
    }
    return Math.round((end.getTime() - start.getTime()) / 86_400_000) + 1;
});

const canAddCategory = computed(
    () => newCategoryName.value.trim().length > 0 && !addingCategory.value,
);

function openNewCategory(): void {
    showNewCategory.value = true;
    categoryError.value = '';
    void nextTick(() => newCategoryInput.value?.focus());
}

function cancelNewCategory(): void {
    showNewCategory.value = false;
    newCategoryName.value = '';
    categoryError.value = '';
}

async function addCategory(): Promise<void> {
    const name = newCategoryName.value.trim();
    if (!name || addingCategory.value) return;

    categoryError.value = '';
    addingCategory.value = true;

    try {
        const csrf = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        const response = await fetch('/expenses/categories', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name }),
        });

        const payload = (await response.json().catch(() => null)) as {
            category?: { value: number | string; label: string };
            message?: string;
            errors?: { name?: string[] };
        } | null;

        if (!response.ok || !payload?.category) {
            categoryError.value =
                payload?.errors?.name?.[0] ??
                payload?.message ??
                t('Could not add category');
            return;
        }

        const option = {
            value: String(payload.category.value),
            label: payload.category.label,
        };
        const existing = categoryList.value.find(
            (item) => item.value === option.value,
        );
        if (!existing) {
            categoryList.value = [...categoryList.value, option].sort((a, b) =>
                a.label.localeCompare(b.label),
            );
        }
        form.expense_category_id = option.value;
        cancelNewCategory();
    } finally {
        addingCategory.value = false;
    }
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            expense_category_id: Number(data.expense_category_id),
            amount: data.amount === '' ? null : data.amount,
            period_end:
                !data.period_end || data.period_end === data.expense_date
                    ? null
                    : data.period_end,
            vendor: data.vendor.trim() === '' ? null : data.vendor.trim(),
            notes: data.notes.trim() === '' ? null : data.notes,
        }))
        .post('/expenses');
}

defineOptions(resourcePageBreadcrumbs('Expenses', '/expenses', 'create'));
</script>

<template>
    <Head :title="t('Add expense')" />

    <MisPage narrow>
        <MisForm
            :processing="form.processing"
            :errors="form.errors"
            show-error-summary
            cancel-href="/expenses"
            :submit-label="t('Save expense')"
            max-width="lg"
            @submit="submit"
        >
            <div class="grid gap-5">
                <div class="space-y-2">
                    <MisSelectField
                        id="expense_category_id"
                        v-model="form.expense_category_id"
                        :label="t('Category')"
                        :error="form.errors.expense_category_id || categoryError"
                        :options="categoryList"
                        :placeholder="t('Select category')"
                        required
                    />
                    <button
                        v-if="!showNewCategory"
                        type="button"
                        class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground transition hover:text-foreground"
                        @click="openNewCategory"
                    >
                        <Plus class="size-3.5" />
                        {{ t('Add new category') }}
                    </button>
                    <div v-else class="flex gap-2">
                        <input
                            ref="newCategoryInput"
                            v-model="newCategoryName"
                            type="text"
                            class="h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-3 text-sm outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                            :placeholder="t('New category name')"
                            :disabled="addingCategory"
                            @keydown.enter.prevent="addCategory"
                            @keydown.escape.prevent="cancelNewCategory"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9 shrink-0 gap-1.5"
                            :disabled="!canAddCategory"
                            @click="addCategory"
                        >
                            {{ t('Add') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-9 shrink-0"
                            :disabled="addingCategory"
                            @click="cancelNewCategory"
                        >
                            {{ t('Cancel') }}
                        </Button>
                    </div>
                </div>

                <MisInputField
                    id="amount"
                    v-model="form.amount"
                    type="number"
                    prefix="€"
                    step="0.01"
                    min="0.01"
                    :label="t('Amount')"
                    :error="form.errors.amount"
                    required
                />

                <div class="space-y-2">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <MisInputField
                            id="expense_date"
                            v-model="form.expense_date"
                            type="date"
                            :label="t('From')"
                            :error="form.errors.expense_date"
                            required
                        />
                        <MisInputField
                            id="period_end"
                            v-model="form.period_end"
                            type="date"
                            :label="t('To')"
                            :error="form.errors.period_end"
                            :hint="t('Same day for a single expense')"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ periodDays }}
                        {{ periodDays === 1 ? t('day') : t('days') }}
                    </p>
                </div>

                <MisInputField
                    id="vendor"
                    v-model="form.vendor"
                    :label="t('Vendor')"
                    :placeholder="t('Optional — who was paid')"
                    :error="form.errors.vendor"
                />

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
