<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    KeyRound,
    MapPin,
    ShieldAlert,
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
import { useCalendar } from '@/composables/useCalendar';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';

type UserSummary = {
    id: number;
    name: string;
    email: string;
};

type AuthenticationLogRecord = {
    id: number;
    email: string | null;
    event: string;
    success: boolean;
    failure_reason: string | null;
    ip_address: string | null;
    ip_addresses: string[] | null;
    user_agent: string | null;
    device_type: string | null;
    browser: string | null;
    platform: string | null;
    session_id: string | null;
    guard: string | null;
    request_method: string | null;
    request_path: string | null;
    referer: string | null;
    accept_language: string | null;
    country: string | null;
    country_code: string | null;
    region: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    timezone: string | null;
    isp: string | null;
    org: string | null;
    asn: string | null;
    is_private_ip: boolean;
    location_label: string | null;
    metadata: Record<string, unknown> | null;
    logged_at: string;
    user?: UserSummary | null;
};

const props = defineProps<{
    logs: Paginated<AuthenticationLogRecord>;
    events: string[];
    summary: {
        total: number;
        today: number;
        failed: number;
        users: number;
    };
    filters?: {
        search?: string | null;
        event?: string | null;
        success?: string | null;
        per_page?: number | null;
    };
}>();

const { t } = useTranslations();
const calendar = useCalendar();
const search = ref(props.filters?.search ?? '');
const event = ref(props.filters?.event ?? '');
const success = ref(props.filters?.success ?? '');
const detailLog = ref<AuthenticationLogRecord | null>(null);
const selected = ref<Array<string | number>>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const filtersActive = computed(
    () => Boolean(event.value || success.value),
);

const columns = computed(() => [
    { key: 'user', label: t('User'), sortable: true },
    { key: 'event', label: t('Event'), sortable: true },
    {
        key: 'status',
        label: t('Status'),
        sortable: true,
        width: '7.5rem',
        type: 'status' as const,
    },
    { key: 'ip', label: t('IP'), mono: true, truncate: true },
    { key: 'location', label: t('Location'), truncate: true },
    {
        key: 'when',
        label: t('When'),
        sortable: true,
        width: '10rem',
    },
]);

const tableRows = computed(() =>
    props.logs.data.map((log) => ({
        ...log,
        user: displayName(log),
        event: log.event,
        status: log.success ? 'success' : 'failed',
        ip: log.ip_address ?? '—',
        location: locationText(log),
        when: formatDateTime(log.logged_at),
    })),
);

const eventOptions = computed(() => [
    { value: '', label: t('All') },
    ...props.events.map((item) => ({
        value: item,
        label: eventLabel(item),
    })),
]);

const successOptions = computed(() => [
    { value: '', label: t('All') },
    { value: '1', label: t('Success') },
    { value: '0', label: t('Failed') },
]);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters?.search ?? '';
        event.value = filters?.event ?? '';
        success.value = filters?.success ?? '';
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
        '/settings/login-logs',
        {
            search: search.value || undefined,
            event: event.value || undefined,
            success: success.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function clearFilters(): void {
    event.value = '';
    success.value = '';
    applyFilters();
}

function eventLabel(value: string): string {
    const labels: Record<string, string> = {
        login_success: t('Successful login'),
        login_failed: t('Failed login'),
        logout: t('Logout'),
        two_factor_challenged: t('Two-factor challenge'),
        two_factor_failed: t('Failed two-factor'),
        lockout: t('Rate limited'),
        session_revoked: t('Session revoked'),
    };

    return labels[value] ?? value.replace(/_/g, ' ');
}

function failureReasonLabel(reason: string | null): string {
    if (!reason) {
        return '—';
    }

    const labels: Record<string, string> = {
        invalid_credentials: t('Invalid credentials'),
        invalid_two_factor_code: t('Invalid two-factor code'),
        rate_limited: t('Too many attempts'),
        account_inactive: t('Account inactive'),
    };

    return labels[reason] ?? reason.replace(/_/g, ' ');
}

function displayName(log: AuthenticationLogRecord): string {
    return log.user?.name ?? log.email ?? t('Unknown user');
}

function locationText(log: AuthenticationLogRecord): string {
    if (log.location_label) {
        return log.location_label;
    }

    if (log.is_private_ip) {
        return t('Local / private network');
    }

    return t('Location unavailable');
}

function mapUrl(log: AuthenticationLogRecord): string | null {
    if (log.latitude == null || log.longitude == null) {
        return null;
    }

    return `https://www.openstreetmap.org/?mlat=${log.latitude}&mlon=${log.longitude}#map=10/${log.latitude}/${log.longitude}`;
}

function formatDateTime(value: string): string {
    return calendar.formatDateTime(value) || value;
}

function logFromRow(row: Record<string, unknown>): AuthenticationLogRecord | null {
    const id = Number(row.id);
    return props.logs.data.find((log) => log.id === id) ?? null;
}

function openDetail(payload: { row: Record<string, unknown> }): void {
    detailLog.value = logFromRow(payload.row);
}

function detailRows(
    log: AuthenticationLogRecord,
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

    push(t('User'), log.user?.name);
    push(t('Email'), log.email ?? log.user?.email);
    push(t('IP address'), log.ip_address);
    if (log.ip_addresses?.length) {
        push(t('All IPs'), log.ip_addresses.join(', '));
    }
    push(t('Location'), locationText(log));
    push(t('City'), log.city);
    push(t('Region'), log.region);
    push(
        t('Country'),
        log.country
            ? `${log.country}${log.country_code ? ` (${log.country_code})` : ''}`
            : null,
    );
    if (log.latitude != null && log.longitude != null) {
        push(t('Coordinates'), `${log.latitude}, ${log.longitude}`);
    }
    push(t('Timezone'), log.timezone);
    push(t('ISP'), log.isp);
    push(t('Organization'), log.org);
    push(t('ASN'), log.asn);
    push(t('Browser'), log.browser);
    push(t('Platform'), log.platform);
    push(t('Device'), log.device_type);
    push(t('Language'), log.accept_language);
    if (log.request_method && log.request_path) {
        push(t('Request'), `${log.request_method} ${log.request_path}`);
    }
    push(t('Referer'), log.referer);
    push(t('Session ID'), log.session_id);
    push(t('Guard'), log.guard);
    push(
        t('Failure reason'),
        log.failure_reason ? failureReasonLabel(log.failure_reason) : null,
    );

    const remember = log.metadata?.remember;
    if (typeof remember === 'boolean') {
        push(t('Remember me'), remember ? t('Yes') : t('No'));
    }

    const message = log.metadata?.message;
    if (typeof message === 'string' && message !== '') {
        push(t('Notes'), message);
    }

    const host = log.metadata?.host;
    if (typeof host === 'string' && host !== '') {
        push(t('Host'), host);
    }

    const isSecure = log.metadata?.is_secure;
    if (typeof isSecure === 'boolean') {
        push(t('HTTPS'), isSecure ? t('Yes') : t('No'));
    }

    const headers = log.metadata?.headers;
    if (headers && typeof headers === 'object' && !Array.isArray(headers)) {
        for (const [key, value] of Object.entries(headers)) {
            if (typeof value === 'string' && value !== '') {
                push(key, value);
            }
        }
    }

    return rows;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/login-logs' },
            { title: 'Login logs', href: '/settings/login-logs' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Login logs')" />

    <SettingsPage>
        <template #tabs>
            <ActivitySettingsTabs />
        </template>

        <div class="space-y-4">
            <MisStatGrid :columns="4">
                <MisStatCard
                    :label="t('Total logins')"
                    :value="summary.total"
                    :icon="KeyRound"
                />
                <MisStatCard
                    :label="t('Today')"
                    :value="summary.today"
                    :icon="CalendarDays"
                    tone="info"
                />
                <MisStatCard
                    :label="t('Failed')"
                    :value="summary.failed"
                    :icon="ShieldAlert"
                    :tone="summary.failed > 0 ? 'danger' : 'muted'"
                />
                <MisStatCard
                    :label="t('Users')"
                    :value="summary.users"
                    :icon="Users"
                    tone="success"
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
            export-filename="login-logs"
            :search-placeholder="t('User, email, IP, city, country, ISP…')"
            :empty-label="t('No login logs yet')"
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
                            {{ t('Status') }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="option in successOptions"
                                :key="`success-${option.value || 'all'}`"
                                type="button"
                                class="kit-chip-toggle"
                                :class="success === option.value ? 'is-on' : ''"
                                @click="success = option.value"
                            >
                                {{ option.label }}
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

            <template #cell-event="{ row }">
                <span class="capitalize">{{
                    eventLabel(String(row.event ?? ''))
                }}</span>
            </template>

            <template #cell-status="{ row }">
                <MisStatusBadge
                    :status="row.success ? 'success' : 'failed'"
                    :label="row.success ? t('Success') : t('Failed')"
                />
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
                    <DialogTitle>
                        {{ detailLog ? displayName(detailLog) : '' }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="detailLog">
                            {{ eventLabel(detailLog.event) }}
                            ·
                            {{ formatDateTime(detailLog.logged_at) }}
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="detailLog" class="space-y-4 text-sm">
                    <MisDetailGrid :columns="2" :items="detailRows(detailLog)" />

                    <div v-if="detailLog.user_agent" class="min-w-0">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ t('User agent') }}
                        </p>
                        <p class="mt-0.5 break-all text-muted-foreground">
                            {{ detailLog.user_agent }}
                        </p>
                    </div>

                    <div v-if="mapUrl(detailLog)" class="flex flex-wrap gap-2">
                        <Button as-child variant="outline" size="sm">
                            <a
                                :href="mapUrl(detailLog)!"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MapPin class="size-4" />
                                {{ t('View on map') }}
                            </a>
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </SettingsPage>
</template>
