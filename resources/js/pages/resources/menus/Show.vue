<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Can from '@/components/Can.vue';
import {
    MisDetailGrid,
    MisSection,
} from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    item: {
        id: number;
        title: string;
        description?: string | null;
        image_url?: string | null;
        menu_items_count?: number | null;
        created_at?: string | null;
        updated_at?: string | null;
    };
}>();

const { t } = useTranslations();

const details = computed(() => [
    { label: t('Title'), value: props.item.title },
    { label: t('Items'), value: props.item.menu_items_count ?? 0 },
    {
        label: t('Created'),
        value: props.item.created_at
            ? new Date(props.item.created_at).toLocaleString()
            : '—',
    },
    {
        label: t('Updated'),
        value: props.item.updated_at
            ? new Date(props.item.updated_at).toLocaleString()
            : '—',
    },
]);

function destroy(): void {
    if (!confirm(t('This action cannot be undone.'))) return;
    router.delete(`/menus/${props.item.id}`);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Menus', href: '/menus' },
            { title: 'Details', href: '/menus' },
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
<div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold tracking-tight">{{ item.title }}</h1>
            <div class="flex flex-wrap gap-2">
                <Can permission="menus.edit">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="`/menus/${item.id}/edit`">
                            <Pencil class="size-4" />
                            {{ t('Edit') }}
                        </Link>
                    </Button>
                </Can>
                <Can permission="menus.delete">
                    <Button variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="size-4" />
                        {{ t('Delete') }}
                    </Button>
                </Can>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
            <div
                class="flex aspect-square items-center justify-center overflow-hidden rounded-xl border bg-muted/30"
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
        </SettingsPanel>
    </SettingsPage>
</template>
