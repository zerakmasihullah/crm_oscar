<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import {
    MisDataTable,
    MisPagination,
    type MisDataTableColumn,
} from '@/components/mis';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import type { RowActionItem } from '@/lib/row-actions';

type MenuRow = {
    id: number;
    title: string;
    description?: string | null;
    image_url?: string | null;
    menu_items_count?: number | null;
};

const props = defineProps<{
    items: Paginated<MenuRow>;
    filters?: { search?: string | null };
}>();

const { t } = useTranslations();
const search = ref(props.filters?.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let syncingFromProps = false;

watch(
    () => props.filters?.search,
    (value) => {
        const next = value ?? '';
        if (search.value === next) return;
        syncingFromProps = true;
        search.value = next;
        syncingFromProps = false;
    },
);

watch(search, (value) => {
    if (syncingFromProps) return;
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value.trim();
        if (next === (props.filters?.search ?? '').trim()) return;
        router.get(
            '/menus',
            { search: next || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 300);
});

const columns = computed<MisDataTableColumn[]>(() => [
    { key: 'image', label: t('Image'), width: '4rem' },
    { key: 'title', label: t('Title'), sortable: true },
    { key: 'description', label: t('Description') },
    { key: 'menu_items_count', label: t('Items'), align: 'end' },
    { key: 'actions', label: '', align: 'end', hideable: false },
]);

const tableRows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        description: stripHtml(item.description).slice(0, 80) || '—',
        menu_items_count: item.menu_items_count ?? 0,
    })),
);

function stripHtml(value?: string | null): string {
    if (!value) return '';
    return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function rowActions(item: MenuRow): RowActionItem[] {
    return [
        { label: t('View'), icon: Eye, href: `/menus/${item.id}` },
        { label: t('Edit'), icon: Pencil, href: `/menus/${item.id}/edit` },
        {
            label: t('Delete'),
            icon: Trash2,
            separator: true,
            variant: 'destructive',
            method: 'delete',
            href: `/menus/${item.id}`,
            confirm: {
                title: t('Delete'),
                description: t('This action cannot be undone.'),
                confirmLabel: t('Delete'),
            },
        },
    ];
}

function itemFromRow(row: Record<string, unknown>): MenuRow | null {
    if (typeof row.id !== 'number') return null;
    return row as unknown as MenuRow;
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Menus', href: '/menus' }],
    },
});
</script>

<template>
    <Head :title="t('Menus')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <MisDataTable
            v-model:search="search"
            :columns="columns"
            :rows="tableRows"
            row-key="id"
            toolbar
            searchable
            column-toggle
            export-filename="menus"
            :search-placeholder="t('Search menus…')"
            :empty-label="t('No menus found')"
            clickable
            @row-click="(row) => router.visit(`/menus/${row.id as number}`)"
        >
            <template #actions>
                <Can permission="menus.create">
                    <Link
                        href="/menus/create"
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        :title="t('Create')"
                    >
                        <Plus class="size-4" />
                    </Link>
                </Can>
</template>

            <template #cell-image="{ row }">
                <img
                    v-if="itemFromRow(row)?.image_url"
                    :src="itemFromRow(row)!.image_url!"
                    :alt="itemFromRow(row)!.title"
                    class="size-10 rounded-lg object-cover"
                />
                <div
                    v-else
                    class="flex size-10 items-center justify-center rounded-lg bg-muted text-xs text-muted-foreground"
                >
                    —
                </div>
            </template>

            <template #cell-actions="{ row }">
                <div v-if="itemFromRow(row)" class="flex justify-end">
                    <RowActionsMenu :actions="rowActions(itemFromRow(row)!)" />
                </div>
            </template>

            <template #footer>
                <MisPagination :pagination="items" />
            </template>
        </MisDataTable>
    </SettingsPage>
</template>
