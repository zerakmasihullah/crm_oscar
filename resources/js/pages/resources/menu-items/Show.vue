<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Ban, CheckCircle2, Pencil, Trash2, Utensils } from '@lucide/vue';
import { computed } from 'vue';
import Can from '@/components/Can.vue';
import {
    MisDetailGrid,
    MisPageHeader,
    MisSection,
    MisStatusBadge,
} from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

type Attachment = { id: number; name: string; price: number };
type Size = { id: number; size: string; price: number };
type Topping = {
    id: number;
    name: string;
    required: number;
    is_optional: boolean;
    attachments: Attachment[];
};

const props = defineProps<{
    item: {
        id: number;
        title: string;
        price?: number | null;
        status: boolean;
        description?: string | null;
        image_url?: string | null;
        menu?: { id: number; title: string } | null;
        total_sold?: number;
        total_revenue?: number;
        sizes?: Size[];
        toppings?: Topping[];
        created_at?: string | null;
        updated_at?: string | null;
    };
}>();

const { t } = useTranslations();

function formatMoney(value?: number | null): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
}

const details = computed(() => [
    { label: t('Menu'), value: props.item.menu?.title ?? '—' },
    { label: t('Title'), value: props.item.title },
    { label: t('Price'), value: formatMoney(props.item.price) },
    { label: t('Total Sold'), value: props.item.total_sold ?? 0 },
    {
        label: t('Total Revenue'),
        value: formatMoney(props.item.total_revenue ?? 0),
    },
    {
        label: t('Created'),
        value: props.item.created_at
            ? new Date(props.item.created_at).toLocaleString()
            : '—',
    },
]);

function toggleStatus(): void {
    router.patch(`/menu-items/${props.item.id}/toggle-status`, {}, {
        preserveScroll: true,
    });
}

function destroy(): void {
    if (!confirm(t('This action cannot be undone.'))) {
        return;
    }

    router.delete(`/menu-items/${props.item.id}`);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Menu Items', href: '/menu-items' },
            { title: 'View', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="item.title" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
<MisPageHeader :title="item.title" :icon="Utensils">
            <template #description>
                <div class="flex items-center gap-2 pt-1">
                    <MisStatusBadge
                        :status="item.status ? 'active' : 'paused'"
                    />
                </div>
            </template>
            <template #actions>
                <Can permission="menu_items.edit">
                    <Button variant="outline" @click="toggleStatus">
                        <component
                            :is="item.status ? Ban : CheckCircle2"
                            class="size-4"
                        />
                        {{ item.status ? t('Deactivate') : t('Activate') }}
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="`/menu-items/${item.id}/edit`">
                            <Pencil class="size-4" />
                            {{ t('Edit') }}
                        </Link>
                    </Button>
                </Can>
                <Can permission="menu_items.delete">
                    <Button variant="destructive" @click="destroy">
                        <Trash2 class="size-4" />
                        {{ t('Delete') }}
                    </Button>
                </Can>
            </template>
        </MisPageHeader>

        <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
            <div
                class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-border/60 bg-muted/30"
            >
                <img
                    v-if="item.image_url"
                    :src="item.image_url"
                    :alt="item.title"
                    class="size-full object-cover"
                />
                <span v-else class="text-sm text-muted-foreground">{{
                    t('No image')
                }}</span>
            </div>

            <div class="space-y-6">
                <MisSection :title="t('Details')">
                    <MisDetailGrid :items="details" :columns="2" />
                </MisSection>

                <MisSection :title="t('Description')">
                    <div
                        v-if="item.description"
                        class="prose prose-sm max-w-none dark:prose-invert"
                        v-html="item.description"
                    />
                    <p v-else class="text-sm text-muted-foreground">—</p>
                </MisSection>
            </div>
        </div>

        <MisSection
            v-if="item.sizes?.length"
            :title="t('Sizes')"
            class="mt-2"
        >
            <ul class="divide-y rounded-xl border border-border/60">
                <li
                    v-for="size in item.sizes"
                    :key="size.id"
                    class="flex items-center justify-between px-4 py-3 text-sm"
                >
                    <span>{{ size.size }}</span>
                    <span class="tabular-nums">{{
                        formatMoney(size.price)
                    }}</span>
                </li>
            </ul>
        </MisSection>

        <MisSection
            v-if="item.toppings?.length"
            :title="t('Toppings')"
            class="mt-2"
        >
            <div class="space-y-4">
                <div
                    v-for="topping in item.toppings"
                    :key="topping.id"
                    class="rounded-xl border border-border/60 p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium">{{ topping.name }}</p>
                        <span class="text-xs text-muted-foreground">
                            {{ t('Required') }}: {{ topping.required }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ t('Is Optional') }}:
                            {{ topping.is_optional ? t('Yes') : t('No') }}
                        </span>
                    </div>
                    <ul
                        v-if="topping.attachments?.length"
                        class="mt-3 divide-y rounded-lg bg-muted/30"
                    >
                        <li
                            v-for="attachment in topping.attachments"
                            :key="attachment.id"
                            class="flex items-center justify-between px-3 py-2 text-sm"
                        >
                            <span>{{ attachment.name }}</span>
                            <span class="tabular-nums">{{
                                formatMoney(attachment.price)
                            }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </MisSection>
        </SettingsPanel>
    </SettingsPage>
</template>
