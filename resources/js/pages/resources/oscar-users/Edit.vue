<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    MisForm,
    MisInputField,
    MisPage,
} from '@/components/mis';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

type UserItem = {
    id: number;
    name: string;
    email: string;
    phone_number?: string | null;
    is_active: boolean;
};

const props = defineProps<{
    item: UserItem;
}>();

const { t } = useTranslations();

const form = useForm({
    name: props.item.name,
    email: props.item.email,
    phone_number: props.item.phone_number ?? '',
    password: '',
    password_confirmation: '',
    is_active: props.item.is_active,
});

function submit(): void {
    form
        .transform((data) => ({
            ...data,
            password: data.password || undefined,
            password_confirmation: data.password
                ? data.password_confirmation
                : undefined,
        }))
        .patch(`/oscar-users/${props.item.id}`);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: '/oscar-users' },
            { title: 'Edit', href: '/oscar-users' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Edit customer')" />

    <MisPage narrow>
        <MisForm
            :title="t('Edit customer')"
            :processing="form.processing"
            :errors="form.errors"
            :cancel-href="`/oscar-users/${item.id}`"
            :submit-label="t('Save')"
            max-width="xl"
            @submit="submit"
        >
            <MisInputField
                id="name"
                v-model="form.name"
                :label="t('Name')"
                :error="form.errors.name"
                required
            />
            <MisInputField
                id="email"
                v-model="form.email"
                type="email"
                :label="t('Email')"
                :error="form.errors.email"
                required
            />
            <MisInputField
                id="phone_number"
                v-model="form.phone_number"
                :label="t('Phone')"
                :error="form.errors.phone_number"
            />
            <MisInputField
                id="password"
                v-model="form.password"
                type="password"
                :label="t('Password')"
                :hint="t('Leave blank to keep the current password.')"
                :error="form.errors.password"
            />
            <MisInputField
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                :label="t('Confirm password')"
            />
            <div class="flex items-center gap-2">
                <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="size-4 rounded border"
                />
                <Label for="is_active">{{ t('Active') }}</Label>
            </div>
        </MisForm>
    </MisPage>
</template>
