<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Clock3,
    Layers,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import {
    MisDataTable,
    MisPagination,
    MisStatCard,
    MisStatGrid,
} from '@/components/mis';
import type { MisBulkConfirmRequest } from '@/components/mis';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import StorageSettingsTabs from '@/components/settings/StorageSettingsTabs.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import { Button } from '@/components/ui/button';
import { useCalendar } from '@/composables/useCalendar';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import type { RowActionItem } from '@/lib/row-actions';

type TrashItem = {
    type: string;
    label: string;
    id: string | number;
    title: string;
    deleted_at: string | null;
};

type TrashType = {
    key: string;
    label: string;
};

const props = defineProps<{
    items: Paginated<TrashItem>;
    types: TrashType[];
    summary: {
        total: number;
        today: number;
        types: number;
        retention_days: number;
    };
    filters?: {
        search?: string | null;
        type?: string | null;
    };
    retention_days: number;
}>();

const { t } = useTranslations();
const calendar = useCalendar();
const { can } = usePermissions();

const search = ref(props.filters?.search ?? '');
const type = ref(props.filters?.type ?? '');
const selected = ref<Array<string | number>>([]);
const bulkProcessing = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const filtersActive = computed(() => Boolean(type.value));
const hasItems = computed(() => props.items.data.length > 0);

const columns = computed(() => [
    { key: 'title', label: t('Item'), sortable: true },
    { key: 'label', label: t('Type'), sortable: true, width: '9rem' },
    {
        key: 'deleted',
        label: t('Deleted'),
        sortable: true,
        width: '10rem',
    },
    {
        key: 'actions',
        label: t('Actions'),
        align: 'end' as const,
        sticky: 'end' as const,
        width: '4.5rem',
        hideable: false,
    },
]);

const tableRows = computed(() =>
    props.items.data.map((item) => ({
        ...item,
        rowKey: `${item.type}:${item.id}`,
        title: item.title,
        label: item.label,
        deleted: item.deleted_at
            ? calendar.formatDateTime(item.deleted_at)
            : '—',
        actions: item.id,
    })),
);

const typeOptions = computed(() => [
    { value: '', label: t('All types') },
    ...props.types.map((entry) => ({
        value: entry.key,
        label: entry.label,
    })),
]);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters?.search ?? '';
        type.value = filters?.type ?? '';
    },
);

watch(search, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        const next = value.trim();
        const current = (props.filters?.search ?? '').trim();
        if (next === current) {
            return;
        }
        applyFilters();
    }, 300);
});

function applyFilters(): void {
    router.get(
        '/settings/trash',
        {
            search: search.value || undefined,
            type: type.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function clearFilters(): void {
    type.value = '';
    applyFilters();
}

function itemFromRow(row: Record<string, unknown>): TrashItem | null {
    const typeKey = String(row.type ?? '');
    const id = row.id;
    return (
        props.items.data.find(
            (item) => item.type === typeKey && String(item.id) === String(id),
        ) ?? null
    );
}

function rowActions(item: TrashItem): RowActionItem[] {
    const actions: RowActionItem[] = [];

    if (can('trash.restore')) {
        actions.push({
            label: t('Restore'),
            icon: RotateCcw,
            method: 'post',
            href: '/settings/trash/restore',
            data: { type: item.type, id: item.id },
            confirm: {
                title: t('Restore item?'),
                description: t('This will put the item back where it was.'),
                confirmLabel: t('Restore'),
            },
        });
    }

    if (can('trash.delete')) {
        actions.push({
            label: t('Delete forever'),
            icon: Trash2,
            variant: 'destructive',
            separator: true,
            method: 'delete',
            href: '/settings/trash/item',
            data: { type: item.type, id: item.id },
            confirm: {
                title: t('Delete forever?'),
                description: t(
                    'This cannot be undone. The item will be permanently removed.',
                ),
                confirmLabel: t('Delete forever'),
            },
        });
    }

    return actions;
}

function emptyTrash(): void {
    router.delete('/settings/trash', {
        onBefore: () =>
            window.confirm(
                t(
                    'Permanently delete everything in trash? This cannot be undone.',
                ),
            ),
    });
}

function selectedTrashItems(keys: Array<string | number>): TrashItem[] {
    return keys
        .map((key) => {
            const [typeKey, ...rest] = String(key).split(':');
            const id = rest.join(':');
            return props.items.data.find(
                (item) =>
                    item.type === typeKey && String(item.id) === String(id),
            );
        })
        .filter((item): item is TrashItem => item != null);
}

function askBulkRestore(
    keys: Array<string | number>,
    clear: () => void,
    confirm: (request: MisBulkConfirmRequest) => void,
): void {
    const items = selectedTrashItems(keys);
    if (!items.length) {
        return;
    }

    confirm({
        title: t('Restore'),
        description: t('Restore :count selected items?', {
            count: String(items.length),
        }),
        confirmLabel: t('Restore'),
        onConfirm: () => {
            if (bulkProcessing.value) {
                return;
            }

            bulkProcessing.value = true;
            router.post(
                '/settings/trash/bulk-restore',
                {
                    items: items.map((item) => ({
                        type: item.type,
                        id: item.id,
                    })),
                },
                {
                    preserveScroll: true,
                    onSuccess: () => clear(),
                    onFinish: () => {
                        bulkProcessing.value = false;
                    },
                },
            );
        },
    });
}

function askBulkForceDelete(
    keys: Array<string | number>,
    clear: () => void,
    confirm: (request: MisBulkConfirmRequest) => void,
): void {
    const items = selectedTrashItems(keys);
    if (!items.length) {
        return;
    }

    confirm({
        title: t('Delete forever'),
        description: t('Permanently delete :count selected items?', {
            count: String(items.length),
        }),
        confirmLabel: t('Delete forever'),
        confirmVariant: 'destructive',
        onConfirm: () => {
            if (bulkProcessing.value) {
                return;
            }

            bulkProcessing.value = true;
            router.delete('/settings/trash/bulk', {
                data: {
                    items: items.map((item) => ({
                        type: item.type,
                        id: item.id,
                    })),
                },
                preserveScroll: true,
                onSuccess: () => clear(),
                onFinish: () => {
                    bulkProcessing.value = false;
                },
            });
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/file-manager' },
            { title: 'Trash', href: '/settings/trash' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Trash')" />

    <SettingsPage>
        <template #tabs>
            <StorageSettingsTabs />
        </template>

        <div class="space-y-4">
            <MisStatGrid :columns="4">
                <MisStatCard
                    :label="t('In trash')"
                    :value="summary.total"
                    :icon="Trash2"
                />
                <MisStatCard
                    :label="t('Today')"
                    :value="summary.today"
                    :icon="CalendarDays"
                    tone="info"
                />
                <MisStatCard
                    :label="t('Types')"
                    :value="summary.types"
                    :icon="Layers"
                    tone="warning"
                />
                <MisStatCard
                    :label="t('Retention days')"
                    :value="summary.retention_days"
                    :icon="Clock3"
                    tone="muted"
                />
            </MisStatGrid>

        <MisDataTable
            v-model:selected="selected"
            v-model:search="search"
            :columns="columns"
            :rows="tableRows"
            row-key="rowKey"
            toolbar
            searchable
            selectable
            selection-toggle
            filterable
            :filters-active="filtersActive"
            column-toggle
            export-filename="trash"
            :search-placeholder="t('Search…')"
            :empty-label="t('Trash is empty')"
        >
            <template #actions>
                <Can permission="trash.delete">
                    <Button
                        v-if="hasItems"
                        size="icon"
                        variant="destructive"
                        class="size-8 rounded-lg"
                        :title="t('Empty trash')"
                        :aria-label="t('Empty trash')"
                        @click="emptyTrash"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </Can>
            </template>

            <template #bulk="{ selectedKeys, clear, confirm }">
                <Can permission="trash.restore">
                    <Button
                        size="sm"
                        variant="secondary"
                        class="h-8 gap-1.5 rounded-xl"
                        :disabled="bulkProcessing"
                        @click="askBulkRestore(selectedKeys, clear, confirm)"
                    >
                        <RotateCcw class="size-3.5" />
                        {{ t('Restore') }}
                    </Button>
                </Can>
                <Can permission="trash.delete">
                    <Button
                        size="sm"
                        variant="destructive"
                        class="h-8 gap-1.5 rounded-xl"
                        :disabled="bulkProcessing"
                        @click="
                            askBulkForceDelete(selectedKeys, clear, confirm)
                        "
                    >
                        <Trash2 class="size-3.5" />
                        {{ t('Delete forever') }}
                    </Button>
                </Can>
            </template>

            <template #filters="{ close }">
                <div class="space-y-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ t('Type') }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="option in typeOptions"
                            :key="`type-${option.value || 'all'}`"
                            type="button"
                            class="kit-chip-toggle"
                            :class="type === option.value ? 'is-on' : ''"
                            @click="type = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <Button
                            v-if="filtersActive"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8"
                            @click="clearFilters"
                        >
                            {{ t('Clear') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            class="h-8"
                            @click="
                                applyFilters();
                                close();
                            "
                        >
                            {{ t('Done') }}
                        </Button>
                    </div>
                </div>
            </template>

            <template #cell-title="{ row }">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ row.title }}</p>
                </div>
            </template>

            <template #cell-label="{ value }">
                <span
                    class="inline-flex rounded-md bg-muted/70 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                >
                    {{ value }}
                </span>
            </template>

            <template #cell-deleted="{ value }">
                <span class="text-muted-foreground">{{ value }}</span>
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
        </div>
    </SettingsPage>
</template>
