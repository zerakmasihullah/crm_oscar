<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Files, HardDrive, Search, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    MisDataTable,
    MisPagination,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
} from '@/components/mis';
import ActivitySettingsTabs from '@/components/settings/ActivitySettingsTabs.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCalendar } from '@/composables/useCalendar';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';

type LogFile = {
    name: string;
    size: number;
    modified_at: string;
};

type LogEntry = {
    id: string;
    datetime: string | null;
    logged_at: string | null;
    environment: string | null;
    level: string;
    message: string;
    message_full: string;
    has_stack: boolean;
    stack: string | null;
    raw: string;
};

type ScanInfo = {
    bytes_scanned: number;
    file_size: number;
    truncated: boolean;
    matched: number;
};

const props = defineProps<{
    files: LogFile[];
    entries: Paginated<LogEntry>;
    scan: ScanInfo;
    levels: string[];
    summary: {
        file_count: number;
        total_bytes: number;
        latest_file: string | null;
        latest_modified_at: string | null;
    };
    filters?: {
        file?: string | null;
        level?: string | null;
        search?: string | null;
        date_from?: string | null;
        date_to?: string | null;
        per_page?: number | null;
    };
}>();

const { t } = useTranslations();
const calendar = useCalendar();

const search = ref(props.filters?.search ?? '');
const level = ref(props.filters?.level ?? '');
const file = ref(props.filters?.file ?? props.files[0]?.name ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const perPage = ref(String(props.filters?.per_page ?? 50));
const detailEntry = ref<LogEntry | null>(null);
const selected = ref<Array<string | number>>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const filtersActive = computed(() =>
    Boolean(
        level.value ||
            dateFrom.value ||
            dateTo.value ||
            (perPage.value && perPage.value !== '50'),
    ),
);

const columns = computed(() => [
    {
        key: 'when',
        label: t('Time'),
        sortable: true,
        width: '10rem',
    },
    {
        key: 'level',
        label: t('Level'),
        sortable: true,
        width: '7.5rem',
        type: 'status' as const,
    },
    {
        key: 'environment',
        label: t('Env'),
        width: '6rem',
    },
    {
        key: 'message',
        label: t('Message'),
        truncate: true,
    },
]);

const tableRows = computed(() =>
    props.entries.data.map((entry) => ({
        ...entry,
        when: formatDateTime(entry.datetime),
        level: entry.level,
        environment: entry.environment || '—',
        message: entry.message,
    })),
);

const levelOptions = computed(() => [
    { value: '', label: t('All levels') },
    ...props.levels.map((item) => ({
        value: item,
        label: levelLabel(item),
    })),
]);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters?.search ?? '';
        level.value = filters?.level ?? '';
        file.value = filters?.file ?? props.files[0]?.name ?? '';
        dateFrom.value = filters?.date_from ?? '';
        dateTo.value = filters?.date_to ?? '';
        perPage.value = String(filters?.per_page ?? 50);
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
        '/settings/system-logs',
        {
            file: file.value || undefined,
            level: level.value || undefined,
            search: search.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            per_page: perPage.value !== '50' ? perPage.value : undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function clearFilters(): void {
    level.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    perPage.value = '50';
    applyFilters();
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return calendar.formatDateTime(value) || value;
}

function levelTone(
    value: string,
): 'danger' | 'warning' | 'neutral' | 'info' | 'success' {
    if (['emergency', 'alert', 'critical', 'error'].includes(value)) {
        return 'danger';
    }

    if (value === 'warning') {
        return 'warning';
    }

    if (value === 'debug') {
        return 'neutral';
    }

    if (value === 'info' || value === 'notice') {
        return 'info';
    }

    return 'neutral';
}

function levelLabel(value: string): string {
    const labels: Record<string, string> = {
        emergency: t('Emergency'),
        alert: t('Alert'),
        critical: t('Critical'),
        error: t('Error'),
        warning: t('Warning'),
        notice: t('Notice'),
        info: t('Info'),
        debug: t('Debug'),
    };

    return labels[value] ?? value;
}

function downloadUrl(): string {
    const params = new URLSearchParams();

    if (file.value) {
        params.set('file', file.value);
    }

    const query = params.toString();

    return query
        ? `/settings/system-logs/download?${query}`
        : '/settings/system-logs/download';
}

function deleteFile(): void {
    if (!file.value) {
        return;
    }

    if (!confirm(t('Delete this log file? This cannot be undone.'))) {
        return;
    }

    router.delete('/settings/system-logs', {
        data: { file: file.value },
        preserveScroll: true,
    });
}

function entryFromRow(row: Record<string, unknown>): LogEntry | null {
    const id = String(row.id);
    return props.entries.data.find((entry) => entry.id === id) ?? null;
}

function openDetail(payload: { row: Record<string, unknown> }): void {
    detailEntry.value = entryFromRow(payload.row);
}

function onFileChange(value: string): void {
    file.value = value;
    applyFilters();
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/system-logs' },
            { title: 'System logs', href: '/settings/system-logs' },
        ],
    },
});
</script>

<template>
    <Head :title="t('System logs')" />

    <SettingsPage>
        <template #tabs>
            <ActivitySettingsTabs />
        </template>

        <div class="space-y-4">
            <MisStatGrid>
                <MisStatCard
                    :label="t('Log files')"
                    :value="summary.file_count"
                    :icon="Files"
                    tone="muted"
                />
                <MisStatCard
                    :label="t('Total size')"
                    :value="formatBytes(summary.total_bytes)"
                    :icon="HardDrive"
                    tone="info"
                />
                <MisStatCard
                    :label="t('Matches')"
                    :value="scan.matched"
                    :icon="Search"
                    tone="default"
                    :hint="
                        scan.truncated
                            ? t('Scanned last :scanned of :total', {
                                  scanned: formatBytes(scan.bytes_scanned),
                                  total: formatBytes(scan.file_size),
                              })
                            : null
                    "
                />
            </MisStatGrid>

            <MisDataTable
                v-model:selected="selected"
                v-model:search="search"
                :columns="columns"
                :rows="tableRows"
                row-key="id"
                toolbar
                searchable
                selectable
                selection-toggle
                filterable
                :filters-active="filtersActive"
                column-toggle
                clickable
                export-filename="system-logs"
                :search-placeholder="t('Message, exception, class, path…')"
                :empty-label="
                    files.length === 0
                        ? t('No log files yet')
                        : t('No log entries match your filters')
                "
                @row-click="openDetail"
            >
                <template #actions>
                    <Button
                        as-child
                        size="icon"
                        variant="ghost"
                        class="size-8 rounded-lg"
                    >
                        <a
                            :href="downloadUrl()"
                            :title="t('Download file')"
                            :aria-label="t('Download file')"
                        >
                            <Download class="size-4" />
                        </a>
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 rounded-lg text-destructive hover:text-destructive"
                        :disabled="!file"
                        :title="t('Delete file')"
                        :aria-label="t('Delete file')"
                        @click="deleteFile"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </template>

                <template #filters="{ close }">
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('File') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="item in files"
                                    :key="item.name"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="file === item.name ? 'is-on' : ''"
                                    @click="onFileChange(item.name)"
                                >
                                    {{ item.name }}
                                    ({{ formatBytes(item.size) }})
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Level') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="option in levelOptions"
                                    :key="`level-${option.value || 'all'}`"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="level === option.value ? 'is-on' : ''"
                                    @click="level = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ t('From date') }}
                                </p>
                                <Input v-model="dateFrom" type="date" />
                            </div>
                            <div class="space-y-1.5">
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ t('To date') }}
                                </p>
                                <Input v-model="dateTo" type="date" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Per page') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="count in ['25', '50', '100']"
                                    :key="count"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="perPage === count ? 'is-on' : ''"
                                    @click="perPage = count"
                                >
                                    {{ count }}
                                </button>
                            </div>
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

                <template #cell-level="{ row }">
                    <MisStatusBadge
                        :status="String(row.level ?? '')"
                        :label="levelLabel(String(row.level ?? ''))"
                        :tone="levelTone(String(row.level ?? ''))"
                    />
                </template>

                <template #cell-message="{ row }">
                    <div class="min-w-0">
                        <p class="truncate">{{ row.message }}</p>
                        <p
                            v-if="row.has_stack"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ t('Includes stack trace') }}
                        </p>
                    </div>
                </template>

                <template #footer>
                    <MisPagination :pagination="entries" />
                </template>
            </MisDataTable>
        </div>

        <Dialog
            :open="detailEntry !== null"
            @update:open="(open) => !open && (detailEntry = null)"
        >
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            detailEntry
                                ? levelLabel(detailEntry.level)
                                : t('Log entry')
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="detailEntry">
                            {{ formatDateTime(detailEntry.datetime) }}
                            <template v-if="detailEntry.environment">
                                · {{ detailEntry.environment }}
                            </template>
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="detailEntry" class="space-y-3">
                    <div>
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ t('Full message') }}
                        </p>
                        <pre
                            class="overflow-x-auto rounded-md border bg-background p-3 text-xs leading-relaxed break-all whitespace-pre-wrap"
                            >{{ detailEntry.message_full }}</pre
                        >
                    </div>
                    <div v-if="detailEntry.stack">
                        <p
                            class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ t('Stack trace') }}
                        </p>
                        <pre
                            class="max-h-80 overflow-auto rounded-md border bg-background p-3 text-xs leading-relaxed break-all whitespace-pre-wrap"
                            >{{ detailEntry.stack }}</pre
                        >
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </SettingsPage>
</template>
