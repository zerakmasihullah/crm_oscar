<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    MisForm,
    MisInputField,
    MisPage,
} from '@/components/mis';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = useForm({
    name: '',
    email: '',
    phone_number: '',
    password: '',
    password_confirmation: '',
    is_active: true,
});

function submit(): void {
    form.post('/oscar-users');
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: '/oscar-users' },
            { title: 'Create', href: '/oscar-users/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Create customer')" />

    <MisPage narrow>
        <MisForm
            :title="t('Create customer')"
            :processing="form.processing"
            :errors="form.errors"
            cancel-href="/oscar-users"
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
                :error="form.errors.password"
                required
            />
            <MisInputField
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                :label="t('Confirm password')"
                required
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
