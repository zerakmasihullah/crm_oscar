<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import {
    MisForm,
    MisInputField,
    MisSection,
    MisSelectField,
    MisTextareaField,
} from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useTranslations } from '@/composables/useTranslations';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

const props = defineProps<{
    item: {
        id: number;
        name: string;
        email: string | null;
        phone: string;
        person: string;
        reservation_date: string | null;
        reservation_time: string;
        message: string | null;
        accepted: boolean;
    };
}>();

const { t } = useTranslations();

const form = useForm({
    name: props.item.name,
    email: props.item.email ?? '',
    phone: props.item.phone,
    person: props.item.person,
    reservation_date: props.item.reservation_date ?? '',
    reservation_time: props.item.reservation_time,
    message: props.item.message ?? '',
    accepted: props.item.accepted ? '1' : '0',
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            accepted: data.accepted === '1',
        }))
        .put(`/reservations/${props.item.id}`);
}

defineOptions(resourcePageBreadcrumbs('Reservations', '/reservations', 'edit'));
</script>

<template>
    <Head :title="t('Edit reservation')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MisForm
                :processing="form.processing"
                :errors="form.errors"
                show-error-summary
                :cancel-href="`/reservations/${item.id}`"
                :submit-label="t('Save changes')"
                max-width="full"
                fields-class="gap-8"
                @submit="submit"
            >
                <MisSection
                    :title="t('Edit reservation')"
                    :description="t('Guest details and booking time.')"
                    :icon="CalendarDays"
                >
                    <div class="grid gap-5 sm:grid-cols-2">
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
                        />
                        <MisInputField
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            :label="t('Phone')"
                            :error="form.errors.phone"
                            required
                        />
                        <MisInputField
                            id="person"
                            v-model="form.person"
                            type="number"
                            min="1"
                            :label="t('Guests')"
                            :error="form.errors.person"
                            required
                        />
                        <MisSelectField
                            id="accepted"
                            v-model="form.accepted"
                            class="sm:col-span-2"
                            :label="t('Status')"
                            :options="[
                                { value: '1', label: t('Accepted') },
                                { value: '0', label: t('Rejected') },
                            ]"
                        />
                    </div>
                </MisSection>

                <MisSection :title="t('When')">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <MisInputField
                            id="reservation_date"
                            v-model="form.reservation_date"
                            type="date"
                            :label="t('Date')"
                            :error="form.errors.reservation_date"
                            required
                        />
                        <MisInputField
                            id="reservation_time"
                            v-model="form.reservation_time"
                            type="time"
                            :label="t('Time')"
                            :error="form.errors.reservation_time"
                            required
                        />
                    </div>
                </MisSection>

                <MisSection :title="t('Notes')">
                    <MisTextareaField
                        id="message"
                        v-model="form.message"
                        :label="t('Message')"
                        :error="form.errors.message"
                        :rows="4"
                    />
                </MisSection>
            </MisForm>
        </SettingsPanel>
    </SettingsPage>
</template>
