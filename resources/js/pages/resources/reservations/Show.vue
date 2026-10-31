<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import Can from '@/components/Can.vue';
import {
    MisDetailGrid,
    MisPageHeader,
    MisStatusBadge,
} from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { pageBreadcrumbs } from '@/lib/pageBreadcrumbs';

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

const details = [
    { label: t('Name'), value: props.item.name },
    { label: t('Email'), value: props.item.email },
    { label: t('Phone'), value: props.item.phone },
    { label: t('Guests'), value: props.item.person },
    { label: t('Date'), value: props.item.reservation_date },
    { label: t('Time'), value: props.item.reservation_time },
    { label: t('Message'), value: props.item.message, span: 2 as const },
];

defineOptions(
    pageBreadcrumbs(
        { title: 'Reservations', href: '/reservations' },
        { title: 'View', href: '/reservations' },
    ),
);
</script>

<template>
    <Head :title="item.name" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
<MisPageHeader :title="item.name">
            <template #description>
                <MisStatusBadge
                    :status="item.accepted ? 'success' : 'rejected'"
                    :label="item.accepted ? t('Accepted') : t('Rejected')"
                />
            </template>
            <template #actions>
                <Can permission="reservations.edit">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="`/reservations/${item.id}/edit`">
                            <Pencil class="size-4" />
                            {{ t('Edit') }}
                        </Link>
                    </Button>
                </Can>
            </template>
        </MisPageHeader>

        <MisDetailGrid :items="details" :columns="2" />
        </SettingsPanel>
    </SettingsPage>
</template>
