<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Truck } from '@lucide/vue';
import {
    MisForm,
    MisInputField,
    MisSection,
} from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = useForm({
    delivery_price: '' as string | number,
    free_delivery_limit: '' as string | number,
    service_fees: '' as string | number,
    discount_for_all: '' as string | number,
    min_order: '' as string | number,
});

function submit() {
    form.post('/deliveries');
}
</script>

<template>
    <Head :title="t('Delivery')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MisForm
                :processing="form.processing"
                :errors="form.errors"
                show-error-summary
                :show-actions="true"
                :submit-label="t('Save delivery settings')"
                max-width="full"
                fields-class="gap-8"
                @submit="submit"
            >
                <MisSection
                    :title="t('Delivery fees')"
                    :description="t('Base fee and extras added to delivery orders.')"
                    :icon="Truck"
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <MisInputField
                            id="delivery_price"
                            v-model="form.delivery_price"
                            type="number"
                            prefix="€"
                            step="0.01"
                            min="0"
                            :label="t('Delivery price')"
                            :error="form.errors.delivery_price"
                        />
                        <MisInputField
                            id="service_fees"
                            v-model="form.service_fees"
                            type="number"
                            prefix="€"
                            step="0.01"
                            min="0"
                            :label="t('Service fees')"
                            :error="form.errors.service_fees"
                        />
                        <MisInputField
                            id="discount_for_all"
                            v-model="form.discount_for_all"
                            type="number"
                            prefix="€"
                            step="0.01"
                            min="0"
                            :label="t('Discount for all')"
                            :hint="t('Flat discount applied to every order.')"
                            :error="form.errors.discount_for_all"
                        />
                    </div>
                </MisSection>

                <MisSection
                    :title="t('Order thresholds')"
                    :description="t('Minimum order and free-delivery rules.')"
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <MisInputField
                            id="min_order"
                            v-model="form.min_order"
                            type="number"
                            prefix="€"
                            step="0.01"
                            min="0"
                            :label="t('Minimum order value')"
                            :error="form.errors.min_order"
                        />
                        <MisInputField
                            id="free_delivery_limit"
                            v-model="form.free_delivery_limit"
                            type="number"
                            prefix="€"
                            step="0.01"
                            min="0"
                            :label="t('Free delivery from')"
                            :hint="t('Order total that unlocks free delivery.')"
                            :error="form.errors.free_delivery_limit"
                        />
                    </div>
                </MisSection>
            </MisForm>
        </SettingsPanel>
    </SettingsPage>
</template>
