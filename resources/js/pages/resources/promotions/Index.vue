<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, Eye, Plus, Send } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import {
    MisDataTable,
    MisPage,
    MisPagination,
    MisSelectField,
    MisStatusBadge,
} from '@/components/mis';
import { useMisPage } from '@/composables/useMisPage';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/lib/format';
import type { RowActionItem } from '@/lib/row-actions';
import { resourcePageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type PromotionRecord = {
    id: number;
    subject: string;
    recipient_type: string;
    status: string;
    sent_count: number | null;
    scheduled_at: string | null;
    sent_at: string | null;
};

const props = defineProps<{
    items: Paginated<PromotionRecord>;
    filters?: {
        search?: string | null;
        status?: string | null;
        recipient_type?: string | null;
    };
    audience?: { users: number; newsletter: number; both: number };
}>();

const { t, editAction, deleteAction, viewAction } = useMisPage();
const { can } = usePermissions();
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const recipientType = ref(props.filters?.recipient_type ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(extra: Record<string, string | undefined> = {}) {
    router.get(
        '/promotions',
        {
            search: (extra.search ?? search.value.trim()) || undefined,
            status: (extra.status ?? status.value) || undefined,
            recipient_type: (extra.recipient_type ?? recipientType.value) || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        if (next === (props.filters?.search ?? '').trim()) return;
        applyFilters({ search: next || undefined });
    }, 300);
});

watch([status, recipientType], () => applyFilters());

const columns = computed(() => [
    { key: 'subject', label: t('Subject') },
    { key: 'recipient_type', label: t('Recipients'), width: '8rem' },
    { key: 'status', label: t('Status'), width: '7rem', type: 'status' as const },
    { key: 'sent_count', label: t('Sent'), width: '5rem' },
    { key: 'scheduled_at', label: t('Scheduled'), width: '10rem' },
    { key: 'actions', label: '', width: '3rem' },
]);

function actionsFor(item: PromotionRecord): RowActionItem[] {
    const actions: RowActionItem[] = [
        viewAction(`/promotions/${item.id}`),
        editAction(`/promotions/${item.id}/edit`, 'promotions.edit'),
        {
            label: t('Preview'),
            icon: Eye,
            href: `/promotions/${item.id}/preview`,
        },
    ];

    if (
        can('promotions.send') &&
        (item.status === 'draft' || item.status === 'scheduled')
    ) {
        actions.push({
            label: t('Send now'),
            icon: Send,
            href: `/promotions/${item.id}/send`,
            method: 'post',
            confirm: {
                title: t('Send campaign'),
                description: t(
                    'Queue ":subject" for background sending? Large lists are processed safely in batches.',
                    { subject: item.subject },
                ),
                confirmLabel: t('Send now'),
            },
        });
    }

    if (can('promotions.send') && item.status === 'sent') {
        actions.push({
            label: t('Resend'),
            icon: Send,
            href: `/promotions/${item.id}/resend`,
            method: 'post',
            confirm: {
                title: t('Resend campaign'),
                description: t(
                    'Resend ":subject"? Weekly caps still apply so people are not flooded.',
                    { subject: item.subject },
                ),
                confirmLabel: t('Resend'),
            },
        });
    }

    if (can('promotions.create')) {
        actions.push({
            label: t('Duplicate'),
            icon: Copy,
            href: `/promotions/${item.id}/duplicate`,
            method: 'post',
        });
    }

    actions.push(
        deleteAction(
            {
                href: `/promotions/${item.id}`,
                title: t('Delete promotion'),
                description: t('Delete ":subject"?', { subject: item.subject }),
            },
            'promotions.delete',
        ),
    );

    return actions;
}

defineOptions(resourcePageBreadcrumbs('Promotions', '/promotions'));
</script>

<template>
    <Head :title="t('Email campaigns')" />

    <MisPage>
        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="items.data"
            row-key="id"
            toolbar
            searchable
            filterable
            :filters-active="Boolean(status || recipientType)"
            clickable
            :search-placeholder="t('Search campaigns…')"
            :empty-label="t('No campaigns yet — create your first deal email.')"
            @row-click="(row) => router.visit(`/promotions/${row.id as number}`)"
        >
            <template #actions>
                <Can permission="promotions.create">
                    <Link
                        href="/promotions/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
            </template>
            <template #filters>
                <div class="grid gap-3 sm:grid-cols-2">
                    <MisSelectField
                        id="status"
                        v-model="status"
                        :label="t('Status')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: 'draft', label: t('Draft') },
                            { value: 'scheduled', label: t('Scheduled') },
                            { value: 'processing', label: t('Sending') },
                            { value: 'pending', label: t('Queued') },
                            { value: 'sent', label: t('Sent') },
                        ]"
                    />
                    <MisSelectField
                        id="recipient_type"
                        v-model="recipientType"
                        :label="t('Audience')"
                        :options="[
                            { value: '', label: t('All') },
                            { value: 'users', label: t('Customers') },
                            { value: 'newsletter', label: t('Newsletter') },
                            { value: 'both', label: t('Everyone') },
                        ]"
                    />
                </div>
            </template>
            <template #cell-status="{ row }">
                <MisStatusBadge :status="String(row.status)" />
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as PromotionRecord)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </MisPage>
</template>
