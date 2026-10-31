<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    Inbox,
    MailCheck,
    MailX,
    Send,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    MisDataTable,
    MisPagination,
    MisSection,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
} from '@/components/mis';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCalendar } from '@/composables/useCalendar';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import EmailTabs from '@/pages/settings/Email/EmailTabs.vue';

type MailSettingsPayload = {
    mailer: string;
    from_address: string;
    from_name: string;
    host: string;
    port: number;
    username: string | null;
    password_set: boolean;
    encryption: string;
    mail_alerts: boolean;
    env_mailer: string;
    template?: string;
};

type EmailTemplate = {
    id: string;
    name: string;
    tagline?: string;
    description: string;
    preview: {
        header: string;
        surface: string;
        card: string;
        accent: string;
        button: string;
        text: string;
        muted: string;
        radius: string;
    };
};

type EmailAddress = {
    address: string;
    name: string | null;
};

type EmailLogRecord = {
    id: number;
    mailer: string | null;
    status: string;
    subject: string | null;
    from_address: string | null;
    from_name: string | null;
    to: EmailAddress[] | null;
    cc: EmailAddress[] | null;
    bcc: EmailAddress[] | null;
    message_id: string | null;
    related_type: string | null;
    body_preview: string | null;
    error: string | null;
    headers: Record<string, string> | null;
    metadata: Record<string, unknown> | null;
    logged_at: string;
    user?: { id: number; name: string; email: string } | null;
};

const props = defineProps<{
    tab: 'configuration' | 'templates' | 'logs';
    settings?: MailSettingsPayload | null;
    mailers: string[];
    encryptions?: string[];
    templates?: EmailTemplate[];
    selectedTemplate?: string | null;
    logs?: Paginated<EmailLogRecord>;
    summary?: {
        total: number;
        sent: number;
        failed: number;
        all_time?: number;
    } | null;
    statuses?: string[];
    filters?: {
        search?: string | null;
        status?: string | null;
        mailer?: string | null;
    };
    canManage: boolean;
    canViewLogs: boolean;
}>();

const { t } = useTranslations();
const calendar = useCalendar();
const page = usePage();

const form = useForm({
    mailer: props.settings?.mailer ?? 'log',
    from_address: props.settings?.from_address ?? '',
    from_name: props.settings?.from_name ?? '',
    host: props.settings?.host ?? '',
    port: props.settings?.port ?? 2525,
    username: props.settings?.username ?? '',
    password: '',
    encryption: props.settings?.encryption ?? '',
    mail_alerts: props.settings?.mail_alerts ?? true,
});

const testForm = useForm({
    to: (page.props.auth as { user?: { email?: string } } | undefined)?.user?.email ?? '',
});

const templateForm = useForm({
    template: props.selectedTemplate ?? 'classic',
});

const previewTemplateId = ref(props.selectedTemplate ?? 'classic');
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const mailerFilter = ref(props.filters?.mailer ?? '');
const detailLog = ref<EmailLogRecord | null>(null);
const selected = ref<Array<string | number>>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const appName = computed(
    () => (page.props.name as string | undefined) ?? 'Oscar',
);
const logoUrl = computed(() => (page.props.logo as string | null | undefined) ?? null);

const showSmtpFields = computed(() => form.mailer === 'smtp');
const activeTemplateId = computed(
    () => props.selectedTemplate ?? templateForm.template ?? 'classic',
);
const previewTemplate = computed(
    () =>
        (props.templates || []).find((item) => item.id === previewTemplateId.value) ??
        (props.templates || [])[0] ??
        null,
);
const filtersActive = computed(
    () => Boolean(status.value || mailerFilter.value),
);

const emailLogColumns = computed(() => [
    {
        key: 'status',
        label: t('Status'),
        sortable: true,
        width: '7.5rem',
        type: 'status' as const,
    },
    { key: 'subject', label: t('Subject'), sortable: true, truncate: true },
    { key: 'recipient', label: t('Recipient'), truncate: true },
    { key: 'type', label: t('Type'), width: '8rem' },
    { key: 'when', label: t('When'), sortable: true, width: '10rem' },
]);

const emailLogRows = computed(() =>
    (props.logs?.data ?? []).map((log) => ({
        ...log,
        status: log.status,
        subject: log.subject || t('Untitled email'),
        recipient: primaryRecipient(log),
        type: relatedTypeLabel(log.related_type),
        when: formatDateTime(log.logged_at),
    })),
);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters?.search ?? '';
        status.value = filters?.status ?? '';
        mailerFilter.value = filters?.mailer ?? '';
    },
);

watch(search, (value) => {
    if (props.tab !== 'logs') {
        return;
    }

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

watch(
    () => props.selectedTemplate,
    (value) => {
        if (value) {
            previewTemplateId.value = value;
            templateForm.template = value;
        }
    },
);

function submitSettings(): void {
    form.put('/settings/email', { preserveScroll: true });
}

function sendTest(): void {
    testForm.post('/settings/email/test', { preserveScroll: true });
}

function focusTemplate(templateId: string): void {
    previewTemplateId.value = templateId;
}

function selectTemplate(templateId: string): void {
    previewTemplateId.value = templateId;
    templateForm.template = templateId;
    templateForm.put('/settings/email/templates', { preserveScroll: true });
}

function applyFilters(): void {
    router.get(
        '/settings/email/logs',
        {
            search: search.value || undefined,
            status: status.value || undefined,
            mailer: mailerFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    status.value = '';
    mailerFilter.value = '';
    applyFilters();
}

function openEmailDetail(payload: { row: Record<string, unknown> }): void {
    const id = Number(payload.row.id);
    detailLog.value =
        props.logs?.data.find((log) => log.id === id) ?? null;
}

function formatAddresses(addresses: EmailAddress[] | null | undefined): string {
    if (!addresses?.length) {
        return '—';
    }

    return addresses
        .map((item) => (item.name ? `${item.name} <${item.address}>` : item.address))
        .join(', ');
}

function primaryRecipient(log: EmailLogRecord): string {
    const first = log.to?.[0];

    if (!first) {
        return t('Unknown recipient');
    }

    return first.name || first.address;
}

function formatDateTime(value: string): string {
    return calendar.formatDateTime(value) || value;
}

function statusLabel(value: string): string {
    return value === 'sent' ? t('Sent') : value === 'failed' ? t('Failed') : value;
}

function relatedTypeLabel(value: string | null): string {
    if (!value) {
        return t('System');
    }

    if (value === 'test_email' || value.endsWith('SystemTestEmailNotification')) {
        return t('Test email');
    }

    if (value.endsWith('CriticalAlertNotification')) {
        return t('Critical alert');
    }

    const parts = value.split('\\');

    return parts[parts.length - 1] ?? value;
}

function deliveryRows(log: EmailLogRecord): Array<{ label: string; value: string }> {
    const rows: Array<{ label: string; value: string }> = [];
    const push = (label: string, value: string | number | null | undefined): void => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        rows.push({ label, value: String(value) });
    };

    push(t('From'), log.from_name ? `${log.from_name} <${log.from_address}>` : log.from_address);
    push(t('To'), formatAddresses(log.to));
    push(t('Cc'), formatAddresses(log.cc));
    push(t('Bcc'), formatAddresses(log.bcc));
    push(t('User'), log.user?.name);

    return rows;
}

function technicalRows(log: EmailLogRecord): Array<{ label: string; value: string }> {
    const rows: Array<{ label: string; value: string }> = [];
    const push = (label: string, value: string | number | null | undefined): void => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        rows.push({ label, value: String(value) });
    };

    push(t('Mailer'), log.mailer);
    push(t('Type'), relatedTypeLabel(log.related_type));
    push(t('Message ID'), log.message_id);

    if (log.metadata?.attachment_count != null) {
        push(t('Attachments'), String(log.metadata.attachment_count));
    }

    if (log.headers) {
        for (const [key, value] of Object.entries(log.headers)) {
            if (value) {
                push(key, value);
            }
        }
    }

    return rows;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/email' },
            { title: 'Email', href: '/settings/email' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Email')" />

    <SettingsPage>
        <template #tabs>
            <EmailTabs
                :active="tab"
                :can-manage="canManage"
                :can-view-logs="canViewLogs"
            />
        </template>

        <template v-if="tab === 'configuration' && canManage && settings">
            <SettingsPanel>
                <MisSection
                    :title="t('Email configuration')"
                >
                    <form class="space-y-6" @submit.prevent="submitSettings">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="mailer">{{ t('Mailer') }}</Label>
                                <select
                                    id="mailer"
                                    v-model="form.mailer"
                                    class="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option
                                        v-for="item in mailers"
                                        :key="item"
                                        :value="item"
                                    >
                                        {{ item }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.mailer" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="from_name">{{ t('From name') }}</Label>
                                <Input id="from_name" v-model="form.from_name" />
                                <InputError :message="form.errors.from_name" />
                            </div>

                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="from_address">{{ t('From address') }}</Label>
                                <Input
                                    id="from_address"
                                    v-model="form.from_address"
                                    type="email"
                                />
                                <InputError :message="form.errors.from_address" />
                            </div>
                        </div>

                        <div
                            v-if="showSmtpFields"
                            class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                        >
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="host">{{ t('SMTP host') }}</Label>
                                <Input id="host" v-model="form.host" />
                                <InputError :message="form.errors.host" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="port">{{ t('Port') }}</Label>
                                <Input
                                    id="port"
                                    v-model.number="form.port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                />
                                <InputError :message="form.errors.port" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="encryption">{{ t('Encryption') }}</Label>
                                <select
                                    id="encryption"
                                    v-model="form.encryption"
                                    class="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">{{ t('Auto') }}</option>
                                    <option value="tls">{{ t('TLS (STARTTLS)') }}</option>
                                    <option value="ssl">{{ t('SSL (SMTPS)') }}</option>
                                </select>
                                <InputError :message="form.errors.encryption" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="username">{{ t('Username') }}</Label>
                                <Input id="username" v-model="form.username" autocomplete="off" />
                                <InputError :message="form.errors.username" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="password">{{ t('Password') }}</Label>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="
                                        settings.password_set
                                            ? t('Leave blank to keep current password')
                                            : t('SMTP password')
                                    "
                                />
                                <InputError :message="form.errors.password" />
                            </div>
                        </div>

                        <label class="flex items-start gap-3 rounded-md border p-4">
                            <Checkbox
                                :checked="form.mail_alerts"
                                class="mt-0.5"
                                @update:checked="(v) => (form.mail_alerts = Boolean(v))"
                            />
                            <span>
                                <span class="block font-medium">{{ t('Critical alert emails') }}</span>
                            </span>
                        </label>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                {{ t('Save email settings') }}
                            </Button>
                        </div>
                    </form>
                </MisSection>
            </SettingsPanel>

            <SettingsPanel>
                <MisSection
                    :title="t('Send test email')"
                    :icon="Send"
                >
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="sendTest">
                        <div class="grid min-w-[16rem] flex-1 gap-2">
                            <Label for="test-to">{{ t('Recipient') }}</Label>
                            <Input
                                id="test-to"
                                v-model="testForm.to"
                                type="email"
                                required
                            />
                            <InputError :message="testForm.errors.to" />
                        </div>
                        <Button type="submit" :disabled="testForm.processing">
                            {{ t('Send test') }}
                        </Button>
                    </form>
                </MisSection>
            </SettingsPanel>
        </template>

        <template v-else-if="tab === 'templates' && canManage">
            <SettingsPanel>
                <MisSection :title="t('Email templates')">
                    <template #actions>
                        <Badge v-if="previewTemplate" variant="outline" class="gap-1.5">
                            <Check class="size-3.5" />
                            {{ t('Active') }}:
                            {{ t((templates || []).find((item) => item.id === activeTemplateId)?.name || 'Classic brand') }}
                        </Badge>
                    </template>

                <div class="grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.15fr)]">
                    <div class="space-y-2">
                        <p class="px-1 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                            {{ t('Options') }}
                        </p>
                        <div class="space-y-2">
                            <button
                                v-for="template in templates || []"
                                :key="template.id"
                                type="button"
                                class="flex w-full items-start gap-3 rounded-2xl border px-4 py-3.5 text-left transition"
                                :class="
                                    previewTemplateId === template.id
                                        ? 'border-zinc-900 bg-zinc-900/[0.04] shadow-soft dark:border-white dark:bg-white/[0.04]'
                                        : 'border-border/80 bg-card hover:border-zinc-900/35 dark:hover:border-white/35'
                                "
                                @click="focusTemplate(template.id)"
                            >
                                <span
                                    class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl border"
                                    :style="{
                                        background: template.preview.surface,
                                        borderColor: template.preview.accent,
                                    }"
                                >
                                    <span
                                        class="size-4 rounded-full"
                                        :style="{ background: template.preview.button }"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold tracking-tight">
                                            {{ t(template.name) }}
                                        </span>
                                        <span
                                            v-if="template.tagline"
                                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ t(template.tagline) }}
                                        </span>
                                        <Badge
                                            v-if="activeTemplateId === template.id"
                                            class="gap-1"
                                        >
                                            <Check class="size-3" />
                                            {{ t('Active') }}
                                        </Badge>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <InputError :message="templateForm.errors.template" />
                    </div>

                    <div class="space-y-3">
                        <p class="px-1 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                            {{ t('Preview') }}
                        </p>
                        <div
                            v-if="previewTemplate"
                            class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-soft"
                        >
                            <div
                                class="border-b px-4 py-3"
                                :style="{ background: previewTemplate.preview.surface }"
                            >
                                <div
                                    class="mx-auto max-w-md overflow-hidden border border-black/5 shadow-sm"
                                    :style="{
                                        background: previewTemplate.preview.card,
                                        borderRadius: previewTemplate.preview.radius,
                                    }"
                                >
                                    <div
                                        class="flex items-center justify-center px-5 py-5"
                                        :style="{
                                            background: previewTemplate.preview.header,
                                            borderBottom:
                                                previewTemplate.id === 'formal'
                                                    ? `3px solid ${previewTemplate.preview.accent}`
                                                    : previewTemplate.id === 'minimal'
                                                      ? `4px solid ${previewTemplate.preview.accent}`
                                                      : undefined,
                                        }"
                                    >
                                        <img
                                            v-if="logoUrl"
                                            :src="logoUrl"
                                            :alt="appName"
                                            class="h-8 w-auto object-contain"
                                        />
                                        <span
                                            v-else
                                            class="text-[15px] font-semibold tracking-tight"
                                            :style="{
                                                color:
                                                    previewTemplate.id === 'classic'
                                                        ? '#ffffff'
                                                        : previewTemplate.preview.text,
                                            }"
                                        >
                                            {{ appName }}
                                        </span>
                                    </div>
                                    <div class="space-y-3.5 px-6 py-6">
                                        <p
                                            class="text-[16px] font-semibold"
                                            :style="{ color: previewTemplate.preview.text }"
                                        >
                                            {{ t('Hello!') }}
                                        </p>
                                        <p
                                            class="text-[13.5px] leading-relaxed"
                                            :style="{ color: previewTemplate.preview.muted }"
                                        >
                                            {{
                                                t(
                                                    'This is how your system emails will look with this template.',
                                                )
                                            }}
                                        </p>
                                        <div>
                                            <span
                                                class="inline-block px-4 py-2.5 text-[12px] font-semibold text-white"
                                                :style="{
                                                    background: previewTemplate.preview.button,
                                                    borderRadius:
                                                        previewTemplate.id === 'modern'
                                                            ? '999px'
                                                            : previewTemplate.preview.radius,
                                                }"
                                            >
                                                {{ t('Open dashboard') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="font-semibold tracking-tight">
                                        {{ t(previewTemplate.name) }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-muted-foreground">
                                        {{
                                            activeTemplateId === previewTemplate.id
                                                ? t('Currently used for all system emails')
                                                : t('Click apply to use this for all system emails')
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    :disabled="
                                        templateForm.processing ||
                                        activeTemplateId === previewTemplate.id
                                    "
                                    @click="selectTemplate(previewTemplate.id)"
                                >
                                    <Check class="size-4" />
                                    {{
                                        activeTemplateId === previewTemplate.id
                                            ? t('Selected')
                                            : t('Use this template')
                                    }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
                </MisSection>
            </SettingsPanel>
        </template>

        <template v-else-if="tab === 'logs' && canViewLogs && logs">
            <div class="space-y-4">
                <MisStatGrid>
                    <MisStatCard
                        :label="t('Matched')"
                        :value="
                            summary?.total ??
                            logs.meta?.total ??
                            logs.data.length
                        "
                        :icon="Inbox"
                        tone="muted"
                    />
                    <MisStatCard
                        :label="t('Sent')"
                        :value="summary?.sent ?? 0"
                        :icon="MailCheck"
                        tone="success"
                    />
                    <MisStatCard
                        :label="t('Failed')"
                        :value="summary?.failed ?? 0"
                        :icon="MailX"
                        :tone="(summary?.failed ?? 0) > 0 ? 'danger' : 'muted'"
                    />
                </MisStatGrid>

                <MisDataTable
                    v-model:selected="selected"
                    v-model:search="search"
                    :columns="emailLogColumns"
                    :rows="emailLogRows"
                    row-key="id"
                    toolbar
                    searchable
                    selectable
                    selection-toggle
                    filterable
                    :filters-active="filtersActive"
                    column-toggle
                    clickable
                    export-filename="email-logs"
                    :search-placeholder="t('Subject, recipient, type…')"
                    :empty-label="t('No emails logged yet')"
                    @row-click="openEmailDetail"
                >
                    <template #filters="{ close }">
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ t('Status') }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="status === '' ? 'is-on' : ''"
                                        @click="status = ''"
                                    >
                                        {{ t('All') }}
                                    </button>
                                    <button
                                        v-for="item in statuses || []"
                                        :key="item"
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="status === item ? 'is-on' : ''"
                                        @click="status = item"
                                    >
                                        {{ statusLabel(item) }}
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ t('Mailer') }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="
                                            mailerFilter === '' ? 'is-on' : ''
                                        "
                                        @click="mailerFilter = ''"
                                    >
                                        {{ t('All') }}
                                    </button>
                                    <button
                                        v-for="item in mailers"
                                        :key="item"
                                        type="button"
                                        class="kit-chip-toggle"
                                        :class="
                                            mailerFilter === item
                                                ? 'is-on'
                                                : ''
                                        "
                                        @click="mailerFilter = item"
                                    >
                                        {{ item }}
                                    </button>
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-end gap-2 pt-1"
                            >
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

                    <template #cell-status="{ row }">
                        <MisStatusBadge
                            :status="String(row.status ?? '')"
                            :label="statusLabel(String(row.status ?? ''))"
                        />
                    </template>

                    <template #cell-subject="{ row }">
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ row.subject }}
                            </p>
                            <p
                                v-if="row.error"
                                class="mt-0.5 truncate text-xs text-destructive"
                            >
                                {{ row.error }}
                            </p>
                        </div>
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
                            {{
                                detailLog?.subject || t('Untitled email')
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            <template v-if="detailLog">
                                {{ statusLabel(detailLog.status) }}
                                ·
                                {{ formatDateTime(detailLog.logged_at) }}
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="detailLog" class="space-y-4 text-sm">
                        <div
                            v-if="detailLog.error"
                            class="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-destructive"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <span>{{ detailLog.error }}</span>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <section class="rounded-xl border border-border/70 bg-card p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                >
                                    {{ t('Delivery') }}
                                </p>
                                <dl class="mt-3 space-y-2.5">
                                    <div
                                        v-for="row in deliveryRows(detailLog)"
                                        :key="`d-${row.label}`"
                                        class="grid gap-0.5 sm:grid-cols-[6.5rem_minmax(0,1fr)]"
                                    >
                                        <dt class="text-muted-foreground">
                                            {{ row.label }}
                                        </dt>
                                        <dd class="break-all font-medium">
                                            {{ row.value }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="rounded-xl border border-border/70 bg-card p-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                >
                                    {{ t('Technical') }}
                                </p>
                                <dl class="mt-3 space-y-2.5">
                                    <div
                                        v-for="row in technicalRows(detailLog)"
                                        :key="`t-${row.label}`"
                                        class="grid gap-0.5 sm:grid-cols-[6.5rem_minmax(0,1fr)]"
                                    >
                                        <dt class="text-muted-foreground">
                                            {{ row.label }}
                                        </dt>
                                        <dd class="break-all font-medium">
                                            {{ row.value }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        </div>

                        <section
                            v-if="detailLog.body_preview"
                            class="rounded-xl border border-border/70 bg-card p-4"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                            >
                                {{ t('Content preview') }}
                            </p>
                            <p
                                class="mt-3 whitespace-pre-wrap leading-relaxed text-muted-foreground"
                            >
                                {{ detailLog.body_preview }}
                            </p>
                        </section>
                    </div>
                </DialogContent>
            </Dialog>
        </template>
    </SettingsPage>
</template>
