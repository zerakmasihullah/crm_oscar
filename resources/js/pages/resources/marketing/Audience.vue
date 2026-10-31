<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, UserMinus, UserPlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import {
    MisDataTable,
    MisInputField,
    MisPage,
    MisPagination,
    MisSelectField,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
} from '@/components/mis';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useMisPage } from '@/composables/useMisPage';
import type { Paginated } from '@/lib/format';
import type { RowActionItem } from '@/lib/row-actions';

type AudienceRow = {
    id: number;
    email: string;
    unsubscribed: boolean;
    unsubscribed_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    items: Paginated<AudienceRow>;
    filters?: {
        search?: string | null;
        status?: string | null;
    };
    stats: {
        active: number;
        unsubscribed: number;
        total: number;
    };
}>();

const { t, can, deleteAction } = useMisPage();
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const addOpen = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const addForm = useForm({
    email: '',
});

function applyFilters(extra: Record<string, string | undefined> = {}) {
    router.get(
        '/marketing/audience',
        {
            search: (extra.search ?? search.value.trim()) || undefined,
            status: (extra.status ?? status.value) || undefined,
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

watch(status, () => applyFilters());

const columns = computed(() => [
    { key: 'email', label: t('Email') },
    { key: 'status', label: t('Status'), width: '8rem' },
    { key: 'created_at', label: t('Joined'), width: '10rem' },
    { key: 'actions', label: '', width: '3rem' },
]);

const rows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        created_label: item.created_at
            ? new Date(item.created_at).toLocaleDateString()
            : '—',
    })),
);

function actionsFor(item: AudienceRow): RowActionItem[] {
    const actions: RowActionItem[] = [];

    if (can('promotions.edit')) {
        actions.push({
            label: item.unsubscribed ? t('Resubscribe') : t('Unsubscribe'),
            icon: item.unsubscribed ? UserPlus : UserMinus,
            href: `/marketing/audience/${item.id}/toggle`,
            method: 'patch',
        });
    }

    actions.push(
        deleteAction(
            {
                href: `/marketing/audience/${item.id}`,
                title: t('Remove subscriber'),
                description: t('Remove :email from the newsletter?', {
                    email: item.email,
                }),
            },
            'promotions.delete',
        ),
    );

    return actions;
}

function submitAdd() {
    addForm.post('/marketing/audience', {
        preserveScroll: true,
        onSuccess: () => {
            addOpen.value = false;
            addForm.reset();
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Marketing', href: '/marketing' },
            { title: 'Audience', href: '/marketing/audience' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Audience')" />

    <MisPage>
        <MisStatGrid :columns="3" class="mb-4">
            <MisStatCard :label="t('Subscribed')" :value="stats.active" tone="success" />
            <MisStatCard :label="t('Unsubscribed')" :value="stats.unsubscribed" tone="muted" />
            <MisStatCard :label="t('Total')" :value="stats.total" />
        </MisStatGrid>

        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="rows"
            row-key="id"
            toolbar
            searchable
            filterable
            :filters-active="Boolean(status)"
            :search-placeholder="t('Search emails…')"
            :empty-label="t('No newsletter subscribers yet')"
        >
            <template #actions>
                <Can permission="promotions.create">
                    <Button
                        size="icon"
                        class="size-8"
                        :title="t('Add email')"
                        @click="addOpen = true"
                    >
                        <Plus class="size-4" />
                    </Button>
                </Can>
            </template>
            <template #filters>
                <MisSelectField
                    id="audience_status"
                    v-model="status"
                    :label="t('Status')"
                    :options="[
                        { value: '', label: t('All') },
                        { value: 'active', label: t('Subscribed') },
                        { value: 'unsubscribed', label: t('Unsubscribed') },
                    ]"
                />
            </template>
            <template #cell-status="{ row }">
                <MisStatusBadge
                    :status="row.unsubscribed ? 'inactive' : 'active'"
                    :label="row.unsubscribed ? t('Unsubscribed') : t('Subscribed')"
                />
            </template>
            <template #cell-created_at="{ row }">
                {{ row.created_label }}
            </template>
            <template #cell-actions="{ row }">
                <RowActionsMenu :actions="actionsFor(row as AudienceRow)" />
            </template>
            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>

        <Dialog :open="addOpen" @update:open="(v) => (addOpen = v)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('Add subscriber') }}</DialogTitle>
                </DialogHeader>
                <MisInputField
                    id="audience_email"
                    v-model="addForm.email"
                    type="email"
                    :label="t('Email')"
                    :error="addForm.errors.email"
                    required
                />
                <DialogFooter>
                    <Button :disabled="addForm.processing" @click="submitAdd">
                        {{ t('Add') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </MisPage>
</template>
