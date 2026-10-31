<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import Can from '@/components/Can.vue';
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
        name: string;
        discount_type: string;
        discount_value: string | number;
        minimum_purchase_amount: string | number | null;
        days_of_week: string[];
        is_active: boolean;
    };
}>();

const { t } = useTranslations();

const details = [
    { label: t('Name'), value: props.item.name, span: 2 as const },
    {
        label: t('Type'),
        value:
            props.item.discount_type === 'percentage'
                ? t('Percentage')
                : t('Fixed'),
    },
    {
        label: t('Value'),
        value:
            props.item.discount_type === 'percentage'
                ? `${Number(props.item.discount_value)}%`
                : `€${Number(props.item.discount_value).toFixed(2)}`,
    },
    {
        label: t('Min. purchase'),
        value: props.item.minimum_purchase_amount
            ? `€${Number(props.item.minimum_purchase_amount).toFixed(2)}`
            : t('No minimum'),
    },
    {
        label: t('Days'),
        value:
            props.item.days_of_week?.length > 0
                ? props.item.days_of_week.map((d) => d).join(', ')
                : t('All days'),
        span: 2 as const,
    },
];

defineOptions(
    pageBreadcrumbs(
        { title: 'Discounts', href: '/discounts' },
        { title: 'View', href: '/discounts' },
    ),
);
</script>

<template>
    <Head :title="item.name" />

    <MisPage narrow>
        <MisPageHeader :title="item.name">
            <template #description>
                <MisStatusBadge
                    :status="item.is_active ? 'active' : 'inactive'"
                    :label="item.is_active ? t('Active') : t('Inactive')"
                />
            </template>
            <template #actions>
                <Can permission="discounts.edit">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="`/discounts/${item.id}/edit`">
                            <Pencil class="size-4" />
                            {{ t('Edit') }}
                        </Link>
                    </Button>
                </Can>
            </template>
        </MisPageHeader>

        <MisDetailGrid :items="details" :columns="2" />
    </MisPage>
</template>
