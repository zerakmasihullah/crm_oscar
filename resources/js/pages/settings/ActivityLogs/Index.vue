<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    ClipboardList,
    PencilLine,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    MisDataTable,
    MisDetailGrid,
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

type UserSummary = {
    id: number | string;
    name: string;
    email: string | null;
};

type ActivityChange = {
    attribute: string;
    old: unknown;
    new: unknown;
};

type ActivityLogRecord = {
    id: number;
    description: string;
    event: string | null;
    log_name: string | null;
    batch_uuid: string | null;
    causer: UserSummary | null;
    subject_type: string | null;
    subject_id: number | string | null;
    subject_label: string | null;
    changes: ActivityChange[];
    properties: Record<string, unknown>;
    created_at: string | null;
};

const props = defineProps<{
    logs: Paginated<ActivityLogRecord>;
    events: string[];
    logNames: string[];
    subjectTypes: string[];
    attributes: string[];
    causers: UserSummary[];
    summary: {
        total: number;
        today: number;
        updated: number;
        actors: number;
    };
    filters?: {
        search?: string | null;
        event?: string | null;
        log_name?: string | null;
        subject_type?: string | null;
        causer_id?: string | null;
        attribute?: string | null;
        has_changes?: string | null;
        date_from?: string | null;
        date_to?: string | null;
        per_page?: number | null;
    };
}>();

const { t } = useTranslations();
const calendar = useCalendar();

const search = ref(props.filters?.search ?? '');
const event = ref(props.filters?.event ?? '');
const logName = ref(props.filters?.log_name ?? '');
const subjectType = ref(props.filters?.subject_type ?? '');
const causerId = ref(props.filters?.causer_id ?? '');
const attribute = ref(props.filters?.attribute ?? '');
const hasChanges = ref(props.filters?.has_changes ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const perPage = ref(String(props.filters?.per_page ?? 25));
const detailLog = ref<ActivityLogRecord | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const filtersActive = computed(() =>
    Boolean(
        event.value ||
            logName.value ||
            subjectType.value ||
            causerId.value ||
            attribute.value ||
            hasChanges.value ||
            dateFrom.value ||
            dateTo.value ||
            (perPage.value && perPage.value !== '25'),
    ),
);

const columns = computed(() => [
    {
        key: 'when',
        label: t('When'),
        sortable: true,
        width: '9.5rem',
    },
    {
        key: 'event',
        label: t('Event'),
        sortable: true,
        width: '7rem',
        type: 'status' as const,
    },
    { key: 'actor', label: t('Who'), width: '12rem' },
    { key: 'subject', label: t('Subject'), truncate: true },
    { key: 'changes_summary', label: t('Changes'), truncate: true },
]);

const tableRows = computed(() =>
    props.logs.data.map((log) => ({
        ...log,
        when: formatDateTime(log.created_at),
        event: log.event ?? '—',
        actor: actorName(log),
        action: log.description,
        subject: subjectText(log),
        changes_summary: changesSummary(log),
    })),
);

const eventOptions = computed(() => [
    { value: '', label: t('All events') },
    ...props.events.map((item) => ({
        value: item,
        label: eventLabel(item),
    })),
]);

const logNameOptions = computed(() => [
    { value: '', label: t('All') },
    ...props.logNames.map((item) => ({
        value: item,
        label: item,
    })),
]);

const subjectTypeOptions = computed(() => [
    { value: '', label: t('All') },
    ...props.subjectTypes.map((item) => ({
        value: item,
        label: item,
    })),
]);

const attributeOptions = computed(() => [
    { value: '', label: t('All fields') },
    ...props.attributes.map((item) => ({
        value: item,
        label: item,
    })),
]);

const causerOptions = computed(() => [
    { value: '', label: t('Anyone') },
    { value: 'system', label: t('System') },
    ...props.causers.map((item) => ({
        value: String(item.id),
        label: item.name,
    })),
]);

const hasChangesOptions = computed(() => [
    { value: '', label: t('All') },
    { value: '1', label: t('With field changes') },
    { value: '0', label: t('Without field changes') },
]);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters?.search ?? '';
        event.value = filters?.event ?? '';
        logName.value = filters?.log_name ?? '';
        subjectType.value = filters?.subject_type ?? '';
        causerId.value = filters?.causer_id ?? '';
        attribute.value = filters?.attribute ?? '';
        hasChanges.value = filters?.has_changes ?? '';
        dateFrom.value = filters?.date_from ?? '';
        dateTo.value = filters?.date_to ?? '';
        perPage.value = String(filters?.per_page ?? 25);
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
        '/settings/action-logs',
        {
            search: search.value || undefined,
            event: event.value || undefined,
            log_name: logName.value || undefined,
            subject_type: subjectType.value || undefined,
            causer_id: causerId.value || undefined,
            attribute: attribute.value || undefined,
            has_changes: hasChanges.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            per_page: perPage.value !== '25' ? perPage.value : undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function clearFilters(): void {
    event.value = '';
    logName.value = '';
    subjectType.value = '';
    causerId.value = '';
    attribute.value = '';
    hasChanges.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    perPage.value = '25';
    applyFilters();
}

function eventLabel(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const labels: Record<string, string> = {
        created: t('Created'),
        updated: t('Updated'),
        deleted: t('Deleted'),
    };

    return labels[value] ?? value.replace(/_/g, ' ');
}

function eventStatus(value: string | null | undefined): string {
    if (value === 'created') {
        return 'success';
    }
    if (value === 'deleted') {
        return 'failed';
    }
    if (value === 'updated') {
        return 'warning';
    }

    return 'neutral';
}

function actorName(log: ActivityLogRecord): string {
    return log.causer?.name ?? t('System');
}

function actorEmail(log: ActivityLogRecord | Record<string, unknown>): string | null {
    const causer = 'causer' in log ? log.causer : null;
    if (!causer || typeof causer !== 'object') {
        return null;
    }

    const email = (causer as UserSummary).email;

    return typeof email === 'string' && email !== '' ? email : null;
}

function actorInitial(name: string): string {
    const trimmed = name.trim();
    if (!trimmed) {
        return '?';
    }

    return trimmed.charAt(0).toUpperCase();
}

function subjectText(log: ActivityLogRecord): string {
    if (log.subject_label) {
        return log.subject_label;
    }

    return '—';
}

function rowChanges(row: Record<string, unknown>): ActivityChange[] {
    return Array.isArray(row.changes) ? (row.changes as ActivityChange[]) : [];
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? t('Yes') : t('No');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function changesSummary(log: ActivityLogRecord): string {
    if (!log.changes.length) {
        return '—';
    }

    return log.changes
        .map((change) => {
            if (change.old === null || change.old === undefined) {
                return `${change.attribute}: ${formatValue(change.new)}`;
            }

            return `${change.attribute}: ${formatValue(change.old)} → ${formatValue(change.new)}`;
        })
        .join(', ');
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return calendar.formatDateTime(value) || value;
}

function logFromRow(row: Record<string, unknown>): ActivityLogRecord | null {
    const id = Number(row.id);
    return props.logs.data.find((log) => log.id === id) ?? null;
}

function openDetail(payload: { row: Record<string, unknown> }): void {
    detailLog.value = logFromRow(payload.row);
}

function detailRows(
    log: ActivityLogRecord,
): Array<{ label: string; value: string }> {
    const rows: Array<{ label: string; value: string }> = [];

    const push = (
        label: string,
        value: string | number | null | undefined,
    ): void => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        rows.push({ label, value: String(value) });
    };

    push(t('Who'), log.causer?.name);
    push(t('Email'), log.causer?.email);
    push(t('Action'), log.description);
    push(t('Event'), eventLabel(log.event));
    push(t('Log'), log.log_name);
    push(t('Subject type'), log.subject_type);
    push(t('Subject'), log.subject_label);
    push(t('Subject ID'), log.subject_id != null ? String(log.subject_id) : null);
    push(t('Batch'), log.batch_uuid);
    push(t('When'), formatDateTime(log.created_at));

    return rows;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/action-logs' },
            { title: 'Action logs', href: '/settings/action-logs' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Action logs')" />

    <SettingsPage>
        <template #tabs>
            <ActivitySettingsTabs />
        </template>

        <div class="space-y-4">
            <MisStatGrid :columns="4">
                <MisStatCard
                    :label="t('Total actions')"
                    :value="summary.total"
                    :icon="ClipboardList"
                />
                <MisStatCard
                    :label="t('Today')"
                    :value="summary.today"
                    :icon="CalendarDays"
                    tone="info"
                />
                <MisStatCard
                    :label="t('Updates')"
                    :value="summary.updated"
                    :icon="PencilLine"
                    tone="warning"
                />
                <MisStatCard
                    :label="t('Actors')"
                    :value="summary.actors"
                    :icon="Users"
                    tone="success"
                />
            </MisStatGrid>

            <MisDataTable
                v-model:search="search"
                :columns="columns"
                :rows="tableRows"
                row-key="id"
                toolbar
                searchable
                filterable
                :filters-active="filtersActive"
                column-toggle
                clickable
                export-filename="action-logs"
                :search-placeholder="t('Who, action, subject…')"
                :empty-label="t('No action logs yet')"
                @row-click="openDetail"
            >
                <template #filters="{ close }">
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Event') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="option in eventOptions"
                                    :key="`event-${option.value || 'all'}`"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="event === option.value ? 'is-on' : ''"
                                    @click="event = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Who') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="option in causerOptions"
                                    :key="`causer-${option.value || 'all'}`"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="causerId === option.value ? 'is-on' : ''"
                                    @click="causerId = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="subjectTypeOptions.length > 1"
                            class="space-y-1.5"
                        >
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Subject type') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="option in subjectTypeOptions"
                                    :key="`subject-${option.value || 'all'}`"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="
                                        subjectType === option.value
                                            ? 'is-on'
                                            : ''
                                    "
                                    @click="subjectType = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="attributeOptions.length > 1"
                            class="space-y-1.5"
                        >
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Changed field') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="option in attributeOptions"
                                    :key="`attr-${option.value || 'all'}`"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="
                                        attribute === option.value ? 'is-on' : ''
                                    "
                                    @click="attribute = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Changes') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="option in hasChangesOptions"
                                    :key="`changes-${option.value || 'all'}`"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="
                                        hasChanges === option.value
                                            ? 'is-on'
                                            : ''
                                    "
                                    @click="hasChanges = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="logNameOptions.length > 1"
                            class="space-y-1.5"
                        >
                            <p class="text-xs font-medium text-muted-foreground">
                                {{ t('Log') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="option in logNameOptions"
                                    :key="`log-${option.value || 'all'}`"
                                    type="button"
                                    class="kit-chip-toggle"
                                    :class="
                                        logName === option.value ? 'is-on' : ''
                                    "
                                    @click="logName = option.value"
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

                <template #cell-when="{ row }">
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium tabular-nums">
                            {{ String(row.when ?? '—') }}
                        </p>
                        <p class="mt-0.5 truncate text-[11px] text-muted-foreground">
                            {{ String(row.description ?? '') }}
                        </p>
                    </div>
                </template>

                <template #cell-event="{ row }">
                    <MisStatusBadge
                        :status="eventStatus(String(row.event ?? ''))"
                        :label="eventLabel(String(row.event ?? ''))"
                    />
                </template>

                <template #cell-actor="{ row }">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-900/5 text-xs font-semibold text-zinc-700 dark:bg-white/10 dark:text-zinc-200"
                        >
                            {{ actorInitial(String(row.actor ?? '?')) }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ String(row.actor ?? t('System')) }}
                            </p>
                            <p
                                v-if="actorEmail(row)"
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ actorEmail(row) }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #cell-subject="{ row }">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ String(row.subject ?? '—') }}
                        </p>
                        <p
                            v-if="row.subject_type"
                            class="mt-0.5 inline-flex max-w-full items-center rounded-md bg-muted/70 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ String(row.subject_type) }}
                        </p>
                    </div>
                </template>

                <template #cell-changes_summary="{ row }">
                    <div
                        v-if="rowChanges(row).length"
                        class="flex max-w-[22rem] flex-wrap gap-1"
                    >
                        <span
                            v-for="change in rowChanges(row).slice(0, 3)"
                            :key="change.attribute"
                            class="inline-flex max-w-full items-center gap-1 rounded-md bg-muted/70 px-1.5 py-0.5 text-[11px] text-muted-foreground"
                        >
                            <span class="truncate font-medium text-foreground">
                                {{ change.attribute }}
                            </span>
                            <template
                                v-if="
                                    change.old !== null &&
                                    change.old !== undefined
                                "
                            >
                                <span class="truncate max-w-16">{{
                                    formatValue(change.old)
                                }}</span>
                                <ArrowRight class="size-3 shrink-0 opacity-60" />
                            </template>
                            <span class="truncate max-w-16 font-medium text-foreground">{{
                                formatValue(change.new)
                            }}</span>
                        </span>
                        <span
                            v-if="rowChanges(row).length > 3"
                            class="inline-flex items-center rounded-md bg-muted/70 px-1.5 py-0.5 text-[11px] text-muted-foreground"
                        >
                            +{{ rowChanges(row).length - 3 }}
                        </span>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #footer>
                    <MisPagination :pagination="logs" />
                </template>
            </MisDataTable>
        </div>

        <Dialog
            :open="detailLog !== null"
            @update:open="(open) => !open && (detailLog = null)"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <div class="flex flex-wrap items-center gap-2">
                        <DialogTitle class="me-1">
                            {{ detailLog ? actorName(detailLog) : '' }}
                        </DialogTitle>
                        <MisStatusBadge
                            v-if="detailLog"
                            :status="eventStatus(detailLog.event)"
                            :label="eventLabel(detailLog.event)"
                        />
                    </div>
                    <DialogDescription>
                        <template v-if="detailLog">
                            {{ detailLog.description }}
                            ·
                            {{ formatDateTime(detailLog.created_at) }}
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="detailLog" class="space-y-5 text-sm">
                    <MisDetailGrid
                        :columns="2"
                        :items="detailRows(detailLog)"
                    />

                    <div v-if="detailLog.changes.length" class="min-w-0">
                        <p
                            class="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                        >
                            {{ t('Changes') }}
                        </p>
                        <div
                            class="mt-2.5 overflow-hidden rounded-xl border border-border/80"
                        >
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="bg-muted/50 text-[11px] tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-3.5 py-2.5 font-semibold">
                                            {{ t('Field') }}
                                        </th>
                                        <th class="px-3.5 py-2.5 font-semibold">
                                            {{ t('Before') }}
                                        </th>
                                        <th class="px-3.5 py-2.5 font-semibold">
                                            {{ t('After') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="change in detailLog.changes"
                                        :key="change.attribute"
                                        class="border-t border-border/70"
                                    >
                                        <td
                                            class="px-3.5 py-2.5 font-medium text-foreground"
                                        >
                                            {{ change.attribute }}
                                        </td>
                                        <td
                                            class="px-3.5 py-2.5 font-mono text-xs break-all text-muted-foreground"
                                        >
                                            {{ formatValue(change.old) }}
                                        </td>
                                        <td
                                            class="px-3.5 py-2.5 font-mono text-xs break-all text-foreground"
                                        >
                                            {{ formatValue(change.new) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </SettingsPage>
</template>
