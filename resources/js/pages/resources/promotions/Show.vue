<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Clock, Copy, Eye, Pencil, RefreshCw, Send } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import {
    MisDataTable,
    MisDetailGrid,
    MisInputField,
    MisPage,
    MisPageHeader,
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
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { pageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type PromotionItem = {
    id: number;
    subject: string;
    content: string;
    image_url: string | null;
    template: string;
    recipient_type: string;
    status: string;
    scheduled_at: string | null;
    sent_at: string | null;
    sent_count: number | null;
    creator: { id: number; name: string } | null;
};

const props = defineProps<{
    item: PromotionItem;
    stats: {
        recipient_count: number;
        recipient_rows: number;
        sent_rows: number;
        opened_count: number;
        clicked_count: number;
        open_rate: number;
        click_rate: number;
        failed_rows: number;
        skipped_rows: number;
        queued_rows: number;
        progress_percent: number;
    };
    recent_recipients: Array<{
        id: number;
        email: string;
        status: string;
        sent_at: string | null;
        opened_at: string | null;
        clicked_at: string | null;
        attempts: number;
        last_error: string | null;
    }>;
    templates: Array<{ value: string; label: string }>;
}>();

const { t } = useTranslations();
const { can } = usePermissions();
const page = usePage();
const testOpen = ref(false);
const scheduleOpen = ref(false);
const sending = ref(false);

const testForm = useForm({
    email: (page.props.auth as { user?: { email?: string } })?.user?.email ?? '',
});

const scheduleForm = useForm({
    scheduled_at: '',
});

const isSending = computed(
    () => props.item.status === 'pending' || props.item.status === 'processing',
);

const audienceLabel = computed(() => {
    const map: Record<string, string> = {
        users: t('All customers'),
        newsletter: t('Newsletter list'),
        both: t('Customers + newsletter'),
    };
    return map[props.item.recipient_type] ?? props.item.recipient_type;
});

const details = computed(() => [
    { label: t('Subject'), value: props.item.subject, span: 2 as const },
    {
        label: t('Template'),
        value:
            props.templates.find((x) => x.value === props.item.template)?.label ??
            props.item.template,
    },
    { label: t('Audience'), value: audienceLabel.value },
    { label: t('Scheduled at'), value: props.item.scheduled_at },
    { label: t('Sent at'), value: props.item.sent_at },
    { label: t('Created by'), value: props.item.creator?.name },
]);

const recipientColumns = [
    { key: 'email', label: t('Email') },
    { key: 'status', label: t('Status') },
    { key: 'sent_at', label: t('Sent') },
    { key: 'opened_at', label: t('Opened') },
    { key: 'clicked_at', label: t('Clicked') },
];

const canRetryFailed = computed(
    () =>
        props.stats.failed_rows > 0 &&
        !isSending.value &&
        (props.item.status === 'sent' || props.item.status === 'draft'),
);

function sendNow() {
    if (
        !confirm(
            t('Send this campaign to :count people now?', {
                count: String(props.stats.recipient_count),
            }),
        )
    ) {
        return;
    }
    sending.value = true;
    router.post(
        `/promotions/${props.item.id}/send`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function retryFailed() {
    if (
        !confirm(
            t('Retry :count failed emails? Addresses already sent will be skipped.', {
                count: String(props.stats.failed_rows),
            }),
        )
    ) {
        return;
    }
    sending.value = true;
    router.post(
        `/promotions/${props.item.id}/resend`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function duplicate() {
    router.post(`/promotions/${props.item.id}/duplicate`);
}

function submitTest() {
    testForm.post(`/promotions/${props.item.id}/test-email`, {
        onSuccess: () => {
            testOpen.value = false;
        },
    });
}

function submitSchedule() {
    scheduleForm.post(`/promotions/${props.item.id}/schedule`, {
        onSuccess: () => {
            scheduleOpen.value = false;
        },
    });
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function startPolling() {
    stopPolling();
    pollTimer = setInterval(() => {
        router.reload({
            only: ['item', 'stats', 'recent_recipients'],
            preserveScroll: true,
        });
    }, 4000);
}

watch(
    isSending,
    (active) => {
        if (active) startPolling();
        else stopPolling();
    },
    { immediate: true },
);

onUnmounted(() => stopPolling());

defineOptions(
    pageBreadcrumbs(
        { title: 'Promotions', href: '/promotions' },
        { title: 'View', href: '/promotions' },
    ),
);
</script>

<template>
    <Head :title="item.subject" />

    <MisPage>
        <MisPageHeader :title="item.subject">
            <template #description>
                <div class="flex flex-wrap items-center gap-2">
                    <MisStatusBadge :status="item.status" />
                    <span class="text-sm text-muted-foreground">{{ audienceLabel }}</span>
                </div>
            </template>
            <template #actions>
                <Button as-child variant="outline" size="sm">
                    <a :href="`/promotions/${item.id}/preview`" target="_blank" rel="noopener">
                        <Eye class="size-4" />
                        {{ t('Preview') }}
                    </a>
                </Button>
                <Can permission="promotions.edit">
                    <Button
                        v-if="!isSending"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <Link :href="`/promotions/${item.id}/edit`">
                            <Pencil class="size-4" />
                            {{ t('Edit') }}
                        </Link>
                    </Button>
                </Can>
                <Can permission="promotions.send">
                    <Button variant="outline" size="sm" @click="testOpen = true">
                        {{ t('Test') }}
                    </Button>
                    <Button
                        v-if="item.status === 'draft' || item.status === 'scheduled'"
                        size="sm"
                        :disabled="sending || isSending"
                        @click="sendNow"
                    >
                        <Send class="size-4" />
                        {{ t('Send now') }}
                    </Button>
                    <Button
                        v-if="canRetryFailed"
                        variant="secondary"
                        size="sm"
                        :disabled="sending"
                        @click="retryFailed"
                    >
                        <RefreshCw class="size-4" />
                        {{ t('Retry failed') }} ({{ stats.failed_rows }})
                    </Button>
                </Can>
                <Can v-if="item.status === 'draft'" permission="promotions.edit">
                    <Button variant="outline" size="sm" @click="scheduleOpen = true">
                        <Clock class="size-4" />
                        {{ t('Schedule') }}
                    </Button>
                </Can>
                <Can permission="promotions.create">
                    <Button variant="ghost" size="sm" @click="duplicate">
                        <Copy class="size-4" />
                        {{ t('Duplicate') }}
                    </Button>
                </Can>
            </template>
        </MisPageHeader>

        <div
            v-if="isSending"
            class="mb-6 rounded-xl border border-orange-500/30 bg-orange-500/10 p-4"
        >
            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                <span class="font-semibold text-foreground">
                    {{ t('Sending in progress…') }}
                </span>
                <span class="tabular-nums text-muted-foreground">
                    {{ stats.sent_rows }} / {{ stats.recipient_rows || '…' }}
                    ({{ stats.progress_percent }}%)
                </span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-orange-500 transition-all duration-500"
                    :style="{ width: `${Math.max(stats.progress_percent, 2)}%` }"
                />
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                {{ t('Queued') }}: {{ stats.queued_rows }} ·
                {{ t('Failed') }}: {{ stats.failed_rows }} ·
                {{ t('Skipped') }}: {{ stats.skipped_rows }} ·
                {{ t('This page refreshes automatically.') }}
            </p>
        </div>

        <MisStatGrid>
            <MisStatCard :label="t('Audience')" :value="String(stats.recipient_count)" />
            <MisStatCard :label="t('Sent')" :value="String(stats.sent_rows)" />
            <MisStatCard :label="t('Failed')" :value="String(stats.failed_rows)" />
            <MisStatCard :label="t('Skipped')" :value="String(stats.skipped_rows)" />
            <MisStatCard :label="t('Opened')" :value="String(stats.opened_count)" />
            <MisStatCard :label="t('Open rate')" :value="`${stats.open_rate}%`" />
            <MisStatCard :label="t('Clicked')" :value="String(stats.clicked_count)" />
            <MisStatCard :label="t('Click rate')" :value="`${stats.click_rate}%`" />
        </MisStatGrid>

        <MisDetailGrid :items="details" :columns="2" class="mt-6" />

        <div v-if="item.image_url" class="mt-6">
            <img :src="item.image_url" alt="" class="max-h-64 rounded-md object-cover" />
        </div>

        <div class="prose prose-sm mt-6 max-w-none dark:prose-invert" v-html="item.content" />

        <div v-if="recent_recipients.length" class="mt-8 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ t('Recent deliveries') }}</h2>
                <Button as-child variant="outline" size="sm">
                    <Link :href="`/promotion-recipients?promotion_id=${item.id}`">
                        {{ t('View all') }}
                    </Link>
                </Button>
            </div>
            <MisDataTable
                :columns="recipientColumns"
                :rows="recent_recipients"
                row-key="id"
                :toolbar="false"
            >
                <template #cell-status="{ row }">
                    <MisStatusBadge :status="String(row.status ?? 'pending')" />
                </template>
                <template #cell-sent_at="{ row }">
                    {{ row.sent_at ? t('Yes') : '—' }}
                </template>
                <template #cell-opened_at="{ row }">
                    {{ row.opened_at ? t('Yes') : '—' }}
                </template>
                <template #cell-clicked_at="{ row }">
                    {{ row.clicked_at ? t('Yes') : '—' }}
                </template>
            </MisDataTable>
        </div>

        <Dialog :open="testOpen" @update:open="(v) => (testOpen = v)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('Send test email') }}</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    {{ t('Send a preview to yourself before the full campaign.') }}
                </p>
                <MisInputField
                    id="test_email"
                    v-model="testForm.email"
                    type="email"
                    :label="t('Email')"
                    :error="testForm.errors.email"
                    required
                />
                <DialogFooter>
                    <Button :disabled="testForm.processing" @click="submitTest">
                        {{ t('Send test') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="scheduleOpen" @update:open="(v) => (scheduleOpen = v)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('Schedule campaign') }}</DialogTitle>
                </DialogHeader>
                <MisInputField
                    id="schedule_at"
                    v-model="scheduleForm.scheduled_at"
                    type="datetime-local"
                    :label="t('Send at')"
                    :error="scheduleForm.errors.scheduled_at"
                    required
                />
                <DialogFooter>
                    <Button :disabled="scheduleForm.processing" @click="submitSchedule">
                        {{ t('Schedule') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </MisPage>
</template>
