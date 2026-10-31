<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Mail,
    Percent,
    Send,
    Users,
    AlertTriangle,
    Megaphone,
} from '@lucide/vue';
import {
    MisPage,
    MisStatCard,
    MisStatGrid,
    MisStatusBadge,
} from '@/components/mis';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

type Campaign = {
    id: number;
    subject: string;
    status: string;
    recipient_type: string;
    sent_count: number | null;
    sent_at: string | null;
    scheduled_at: string | null;
    created_at: string | null;
};

type FailedRow = {
    id: number;
    email: string;
    last_error: string | null;
    promotion: { id: number; subject: string } | null;
};

defineProps<{
    stats: {
        customers: number;
        newsletter_active: number;
        newsletter_unsubscribed: number;
        reach: number;
        campaigns_total: number;
        campaigns_draft: number;
        campaigns_scheduled: number;
        campaigns_sending: number;
        campaigns_sent: number;
        emails_sent_30d: number;
        emails_failed: number;
        open_rate_30d: number;
        active_discounts: number;
    };
    recent_campaigns: Campaign[];
    failed_sample: FailedRow[];
}>();

const { t } = useTranslations();
const { can } = usePermissions();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Marketing', href: '/marketing' }],
    },
});
</script>

<template>
    <Head :title="t('Marketing')" />

    <MisPage>
        <MisStatGrid :columns="4">
            <MisStatCard
                :label="t('Reach')"
                :value="stats.reach.toLocaleString()"
                :hint="t('Customers + newsletter')"
                :icon="Users"
                tone="info"
            />
            <MisStatCard
                :label="t('Sent (30d)')"
                :value="stats.emails_sent_30d.toLocaleString()"
                :hint="`${stats.open_rate_30d}% ${t('open rate')}`"
                :icon="Send"
                tone="success"
            />
            <MisStatCard
                :label="t('Campaigns')"
                :value="stats.campaigns_total"
                :hint="`${stats.campaigns_draft} ${t('draft')} · ${stats.campaigns_scheduled} ${t('scheduled')}`"
                :icon="Megaphone"
            />
            <MisStatCard
                :label="t('Failed emails')"
                :value="stats.emails_failed"
                :hint="`${stats.active_discounts} ${t('active discounts')}`"
                :icon="AlertTriangle"
                :tone="stats.emails_failed > 0 ? 'warning' : 'muted'"
            />
        </MisStatGrid>

        <!-- Quick actions -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Link
                v-if="can('promotions.create')"
                href="/promotions/create"
                class="group flex items-center gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-soft transition hover:border-orange-500/40 dark:border-white/10"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-orange-500 text-white"
                >
                    <Mail class="size-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{ t('New campaign') }}</span>
                    <span class="block text-xs text-muted-foreground">{{ t('Compose & send email') }}</span>
                </span>
                <ArrowUpRight class="size-4 text-muted-foreground transition group-hover:text-orange-500" />
            </Link>

            <Link
                v-if="can('discounts.create')"
                href="/discounts/create"
                class="group flex items-center gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-soft transition hover:border-orange-500/40 dark:border-white/10"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-muted"
                >
                    <Percent class="size-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{ t('New discount') }}</span>
                    <span class="block text-xs text-muted-foreground">{{ t('Promo codes & offers') }}</span>
                </span>
                <ArrowUpRight class="size-4 text-muted-foreground transition group-hover:text-orange-500" />
            </Link>

            <Link
                v-if="can('promotions.view')"
                href="/marketing/audience"
                class="group flex items-center gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-soft transition hover:border-orange-500/40 dark:border-white/10"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-muted"
                >
                    <Users class="size-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{ t('Audience') }}</span>
                    <span class="block text-xs text-muted-foreground">
                        {{ stats.newsletter_active.toLocaleString() }} {{ t('subscribed') }}
                    </span>
                </span>
                <ArrowUpRight class="size-4 text-muted-foreground transition group-hover:text-orange-500" />
            </Link>

            <Link
                v-if="can('promotion_recipients.view')"
                href="/promotion-recipients?status=failed"
                class="group flex items-center gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-soft transition hover:border-orange-500/40 dark:border-white/10"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-muted"
                >
                    <AlertTriangle class="size-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{ t('Delivery log') }}</span>
                    <span class="block text-xs text-muted-foreground">{{ t('Sent, failed, skipped') }}</span>
                </span>
                <ArrowUpRight class="size-4 text-muted-foreground transition group-hover:text-orange-500" />
            </Link>
        </div>

        <div class="grid gap-4 xl:grid-cols-5">
            <!-- Recent campaigns -->
            <section
                class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-soft xl:col-span-3 dark:border-white/10"
            >
                <div class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-4">
                    <div>
                        <h2 class="text-[15px] font-semibold tracking-tight">
                            {{ t('Recent campaigns') }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.campaigns_sending }} {{ t('sending') }} ·
                            {{ stats.campaigns_sent }} {{ t('sent') }}
                        </p>
                    </div>
                    <Link
                        href="/promotions"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        {{ t('All campaigns') }}
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </div>

                <ul v-if="recent_campaigns.length" class="divide-y divide-border/50">
                    <li
                        v-for="campaign in recent_campaigns"
                        :key="campaign.id"
                    >
                        <Link
                            :href="`/promotions/${campaign.id}`"
                            class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-muted/30"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ campaign.subject }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{
                                        campaign.sent_count
                                            ? `${campaign.sent_count.toLocaleString()} ${t('sent')}`
                                            : campaign.scheduled_at
                                              ? t('Scheduled')
                                              : t('Draft')
                                    }}
                                </p>
                            </div>
                            <MisStatusBadge :status="campaign.status" />
                        </Link>
                    </li>
                </ul>
                <div
                    v-else
                    class="flex flex-col items-center gap-3 px-5 py-12 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t('No campaigns yet') }}
                    </p>
                    <Link
                        v-if="can('promotions.create')"
                        href="/promotions/create"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        {{ t('Create your first campaign') }}
                    </Link>
                </div>
            </section>

            <!-- Failed / health -->
            <section
                class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-soft xl:col-span-2 dark:border-white/10"
            >
                <div class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-4">
                    <div>
                        <h2 class="text-[15px] font-semibold tracking-tight">
                            {{ t('Needs attention') }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ t('Failed deliveries') }}
                        </p>
                    </div>
                    <Link
                        href="/promotion-recipients?status=failed"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        {{ t('View all') }}
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </div>

                <ul v-if="failed_sample.length" class="divide-y divide-border/50">
                    <li
                        v-for="row in failed_sample"
                        :key="row.id"
                        class="px-5 py-3.5"
                    >
                        <p class="truncate text-sm font-medium">{{ row.email }}</p>
                        <p class="mt-0.5 truncate text-xs text-muted-foreground">
                            {{ row.promotion?.subject || t('Campaign') }}
                        </p>
                        <p
                            v-if="row.last_error"
                            class="mt-1 line-clamp-2 text-[11px] text-amber-700 dark:text-amber-300"
                        >
                            {{ row.last_error }}
                        </p>
                    </li>
                </ul>
                <p
                    v-else
                    class="px-5 py-12 text-center text-sm text-muted-foreground"
                >
                    {{ t('All clear — no failed emails') }}
                </p>
            </section>
        </div>
    </MisPage>
</template>
