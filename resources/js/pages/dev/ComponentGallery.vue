<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Archive,
    Inbox,
    Info,
    MailCheck,
    TriangleAlert,
} from '@lucide/vue';
import { ref } from 'vue';
import Can from '@/components/Can.vue';
import {
    MisAlert,
    MisChartCard,
    MisCreateButton,
    MisDataTable,
    MisDetailGrid,
    MisEmptyState,
    MisExpandableRow,
    MisField,
    MisFilterBar,
    MisForm,
    MisInputField,
    MisListPage,
    MisListRow,
    MisLoadingState,
    MisPage,
    MisPageHeader,
    MisSearchInput,
    MisSection,
    MisSegmentedControl,
    MisSelectField,
    MisTextareaField,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
    MisTabs,
    MisToolbar,
} from '@/components/mis';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import SettingsAddButton from '@/components/SettingsAddButton.vue';
import StatusChangeHistory from '@/components/StatusChangeHistory.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { useCalendar } from '@/composables/useCalendar';
import { useTranslations } from '@/composables/useTranslations';
import { localizeDigits } from '@/lib/localized-digits';
import type { RowActionItem } from '@/lib/row-actions';

const { t } = useTranslations();
const calendar = useCalendar();
const sampleDate = ref(new Date().toISOString().slice(0, 10));
const search = ref('');
const tab = ref('overview');
const segment = ref('all');
const rowOpen = ref(false);
const statusFilter = ref('');
const formName = ref('');
const formNotes = ref('');
const formDemoErrors = {
    name: t('Name is required.'),
};

const sampleLogs = [
    {
        id: 1,
        from_status: 'active',
        to_status: 'disabled',
        created_at: new Date().toISOString(),
        changed_by: { id: 1, name: 'Admin' },
    },
];

const sampleActions: RowActionItem[] = [
    { label: t('Edit'), href: '#' },
    {
        label: t('Delete'),
        href: '#',
        variant: 'destructive',
        separator: true,
    },
];

const chartLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
const chartDatasets = [{ label: t('Events'), data: [4, 8, 5, 12, 7] }];

const tableColumns = [
    { key: 'name', label: t('Name') },
    { key: 'status', label: t('Status') },
    {
        key: 'count',
        label: t('Count'),
        align: 'end' as const,
        class: 'tabular-nums',
    },
];

const tableRows = [
    { name: 'Alpha', status: 'active', count: 12 },
    { name: 'Beta', status: 'paused', count: 3 },
    { name: 'Gamma', status: 'failed', count: 1 },
];
</script>

<template>
    <Head :title="t('Component gallery')" />

    <MisPage :title="t('Component gallery')">
        <MisPageHeader
            :title="t('Component gallery')"
            :description="
                t(
                    'Internal reference for kit MIS UI primitives (not Storybook).',
                )
            "
            :icon="Archive"
        >
            <template #actions>
                <SettingsAddButton href="#">{{ t('Create') }}</SettingsAddButton>
            </template>
        </MisPageHeader>

        <MisAlert tone="info" :title="t('MIS kit')">
            {{
                t(
                    'Reuse these primitives across settings, logs, analytics, and resource screens.',
                )
            }}
        </MisAlert>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('Buttons') }}</CardTitle>
                    <CardDescription>{{ t('Primary actions') }}</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Button>{{ t('Save') }}</Button>
                    <Button variant="outline">{{ t('Cancel') }}</Button>
                    <SettingsAddButton href="#">{{
                        t('Create')
                    }}</SettingsAddButton>
                    <MisCreateButton href="#" permission="users.create">{{
                        t('Create')
                    }}</MisCreateButton>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('Permissions') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <Can permission="users.view">
                        <Badge>{{ t('Visible with users.view') }}</Badge>
                    </Can>
                    <div class="flex flex-wrap gap-2">
                        <MisStatusBadge status="active" />
                        <MisStatusBadge status="pending" />
                        <MisStatusBadge status="failed" />
                        <MisStatusBadge status="draft" />
                    </div>
                    <RowActionsMenu :actions="sampleActions" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('Locale helpers') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <p>{{ localizeDigits('1234567890', 'fa') }}</p>
                    <p>{{ calendar.format(new Date()) }}</p>
                    <p>{{ calendar.formatLong(new Date()) }}</p>
                    <MisSearchInput
                        v-model="search"
                        class="max-w-sm"
                        :aria-label="t('Search')"
                        :placeholder="t('Search')"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('Date picker') }}</CardTitle>
                    <CardDescription>
                        {{ t('Uses the organization calendar setting') }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-2">
                    <DatePicker v-model="sampleDate" />
                    <p class="text-muted-foreground text-xs tabular-nums">
                        {{ sampleDate || '—' }}
                    </p>
                </CardContent>
            </Card>

            <StatusChangeHistory :logs="sampleLogs" />
        </div>

        <MisSection
            :title="t('Alerts')"
            :description="t('Inline feedback banners')"
        >
            <div class="grid gap-3">
                <MisAlert tone="info" :icon="Info">
                    {{ t('Informational message for operators.') }}
                </MisAlert>
                <MisAlert tone="warning" :icon="TriangleAlert" :title="t('Attention')">
                    {{ t('There are pending sync failures or conflicts.') }}
                </MisAlert>
                <MisAlert tone="danger" :title="t('Failed')">
                    {{ t('Delivery failed. Check mailer configuration.') }}
                </MisAlert>
                <MisAlert tone="success" :title="t('Saved')">
                    {{ t('Settings updated successfully.') }}
                </MisAlert>
            </div>
        </MisSection>

        <MisListPage
            :title="t('MIS list page')"
            :description="t('Standard Card shell for ERP list screens')"
        >
            <template #actions>
                <SettingsAddButton href="#">{{ t('Create') }}</SettingsAddButton>
            </template>

            <MisToolbar>
                <MisFilterBar
                    variant="plain"
                    :apply-label="t('Apply')"
                    :clear-label="t('Clear')"
                    @apply="() => undefined"
                    @clear="search = ''"
                >
                    <MisField :label="t('Search')" class="min-w-[14rem] flex-1">
                        <MisSearchInput
                            v-model="search"
                            :aria-label="t('Search')"
                        />
                    </MisField>
                    <MisSelectField
                        id="gallery-status"
                        v-model="statusFilter"
                        :label="t('Status')"
                        class="min-w-[8.5rem]"
                        :placeholder="t('All')"
                        :options="[
                            { value: 'active', label: t('Active') },
                            { value: 'failed', label: t('Failed') },
                        ]"
                    />
                </MisFilterBar>
            </MisToolbar>

            <MisListRow>
                <div class="flex items-center justify-between gap-3">
                    <span class="font-medium">{{ t('Sample row') }}</span>
                    <RowActionsMenu :actions="sampleActions" />
                </div>
            </MisListRow>
        </MisListPage>

        <MisSection :title="t('Stats')">
            <MisStatGrid :columns="4">
                <MisStatCard
                    :label="t('Matched')"
                    :value="128"
                    :icon="Inbox"
                    tone="muted"
                />
                <MisStatCard
                    :label="t('Sent')"
                    :value="96"
                    :icon="MailCheck"
                    tone="success"
                    hint="Last 7 days"
                />
                <MisStatCard
                    :label="t('Failed')"
                    :value="4"
                    :icon="TriangleAlert"
                    tone="danger"
                />
                <MisStatCard
                    :label="t('Pending')"
                    :value="12"
                    :icon="Archive"
                    tone="warning"
                />
            </MisStatGrid>
        </MisSection>

        <MisSection :title="t('Tabs')">
            <div class="space-y-4">
                <MisTabs
                    v-model="tab"
                    :tabs="[
                        { id: 'overview', label: t('Overview') },
                        { id: 'logs', label: t('Logs') },
                    ]"
                />
                <MisSegmentedControl
                    v-model="segment"
                    :items="[
                        { id: 'all', label: t('All') },
                        { id: 'active', label: t('Active') },
                    ]"
                />
            </div>
        </MisSection>

        <MisSection :title="t('Expandable row')">
            <MisExpandableRow v-model:open="rowOpen">
                <div class="space-y-1">
                    <p class="font-medium">{{ t('Sample log entry') }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ t('Click to expand details') }}
                    </p>
                </div>
                <template #detail>
                    <MisDetailGrid
                        :columns="3"
                        :items="[
                            { label: t('User'), value: 'Admin' },
                            { label: t('IP address'), value: '10.0.0.1' },
                            { label: t('Status'), value: 'Success' },
                        ]"
                    />
                </template>
            </MisExpandableRow>
        </MisSection>

        <div class="grid gap-6 lg:grid-cols-2">
            <MisChartCard
                :title="t('Chart')"
                type="bar"
                :labels="chartLabels"
                :datasets="chartDatasets"
            />
            <MisSection :title="t('Data table')" content-class="space-y-3">
                <MisDataTable
                    :columns="tableColumns"
                    :rows="tableRows"
                    :empty-label="t('No results')"
                    row-key="name"
                >
                    <template #cell-status="{ value }">
                        <MisStatusBadge :status="String(value)" />
                    </template>
                </MisDataTable>
            </MisSection>
        </div>

        <MisSection :title="t('Empty & loading')">
            <div class="grid gap-4 lg:grid-cols-2">
                <MisEmptyState
                    :icon="Inbox"
                    :title="t('No results')"
                    :description="t('Try clearing filters or broadening your search.')"
                >
                    <template #actions>
                        <Button variant="outline" size="sm">{{
                            t('Clear')
                        }}</Button>
                    </template>
                </MisEmptyState>
                <MisLoadingState :label="t('Loading…')" />
            </div>
        </MisSection>

        <MisSection :title="t('Fields')">
            <div class="grid max-w-xl gap-3 sm:grid-cols-2">
                <MisField :label="t('Name')" html-for="gallery-name" required>
                    <Input id="gallery-name" :placeholder="t('Name')" />
                </MisField>
                <MisSelectField
                    id="gallery-role"
                    v-model="statusFilter"
                    :label="t('Role')"
                    :placeholder="t('All')"
                    :options="[
                        { value: 'owner', label: 'Owner' },
                        { value: 'admin', label: 'Admin' },
                    ]"
                />
            </div>
        </MisSection>

        <MisSection :title="t('Form')">
            <MisForm
                :title="t('Example form')"
                :description="t('Shared form shell with field errors.')"
                :show-actions="true"
                max-width="xl"
                @submit="() => undefined"
            >
                <MisInputField
                    id="gallery-form-name"
                    v-model="formName"
                    :label="t('Name')"
                    :error="formDemoErrors.name"
                    required
                />
                <MisTextareaField
                    id="gallery-form-notes"
                    v-model="formNotes"
                    :label="t('Notes')"
                    :rows="3"
                />
            </MisForm>
        </MisSection>
    </MisPage>
</template>
