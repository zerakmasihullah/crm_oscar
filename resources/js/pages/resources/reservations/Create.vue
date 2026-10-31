<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
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
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

const { t } = useTranslations();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    person: '',
    reservation_date: '',
    reservation_time: '',
    message: '',
    accepted: false,
});

function submit() {
    form.post('/reservations');
}

defineOptions(resourcePageBreadcrumbs('Reservations', '/reservations', 'create'));
</script>

<template>
    <Head :title="t('Create reservation')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MisForm
                :processing="form.processing"
                :errors="form.errors"
                show-error-summary
                cancel-href="/reservations"
                :submit-label="t('Save reservation')"
                max-width="full"
                fields-class="gap-8"
                @submit="submit"
            >
                <MisSection
                    :title="t('Create reservation')"
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
                            required
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
                        :placeholder="t('Special requests or notes…')"
                        :error="form.errors.message"
                        :rows="4"
                    />
                </MisSection>
            </MisForm>
        </SettingsPanel>
    </SettingsPage>
</template>
