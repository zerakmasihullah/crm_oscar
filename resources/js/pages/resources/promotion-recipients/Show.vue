<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    MisDetailGrid,
    MisPage,
    MisPageHeader,
    MisStatusBadge,
} from '@/components/mis';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { pageBreadcrumbs } from '@/lib/pageBreadcrumbs';

const props = defineProps<{
    item: {
        id: number;
        email: string;
        status: string;
        attempts: number;
        sent_at: string | null;
        last_attempt_at: string | null;
        last_error: string | null;
        opened_at: string | null;
        clicked_at: string | null;
        created_at: string | null;
        promotion: {
            id: number;
            subject: string;
            status?: string | null;
            template?: string | null;
        } | null;
    };
}>();

const { t } = useTranslations();

const details = [
    { label: t('Email'), value: props.item.email, span: 2 as const },
    {
        label: t('Promotion'),
        value: props.item.promotion?.subject,
        span: 2 as const,
    },
    { label: t('Status'), value: props.item.status },
    { label: t('Attempts'), value: props.item.attempts },
    { label: t('Sent at'), value: props.item.sent_at },
    { label: t('Last attempt'), value: props.item.last_attempt_at },
    { label: t('Opened at'), value: props.item.opened_at },
    { label: t('Clicked at'), value: props.item.clicked_at },
    { label: t('Created at'), value: props.item.created_at },
    ...(props.item.last_error
        ? [{ label: t('Last error'), value: props.item.last_error, span: 2 as const }]
        : []),
];

defineOptions(
    pageBreadcrumbs(
        { title: 'Promotion recipients', href: '/promotion-recipients' },
        { title: 'View', href: '/promotion-recipients' },
    ),
);
</script>

<template>
    <Head :title="item.email" />

    <MisPage narrow>
        <MisPageHeader :title="item.email">
            <template #description>
                <MisStatusBadge :status="item.status || 'pending'" />
            </template>
            <template #actions>
                <Button
                    v-if="item.promotion"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link :href="`/promotions/${item.promotion.id}`">
                        {{ t('View promotion') }}
                    </Link>
                </Button>
            </template>
        </MisPageHeader>

        <MisDetailGrid :items="details" :columns="2" />
    </MisPage>
</template>
