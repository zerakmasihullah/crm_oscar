<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';
import MisPagination from '@/components/MisPagination.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import { useOfflineResourceList } from '@/offline/useOfflineResourceList';

const props = defineProps<{
    items: Paginated<{ id: number; name: string; client_uuid?: string }>;
    offlineEnabled?: boolean;
}>();

const { t } = useTranslations();
const onlineItems = computed(() => props.items.data);

const { displayItems, showingOfflineCache, online } = useOfflineResourceList({
    resource: 'field_notes',
    offlineEnabled: toRef(props, 'offlineEnabled'),
    onlineItems,
});
</script>

<template>
    <Head :title="t('FieldNote')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('FieldNote') }}</h1>
            <Link
                :href="'/' + 'field-notes/create'"
                class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
            >
                {{ t('Create') }}
            </Link>
        </div>

        <p
            v-if="showingOfflineCache"
            class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-sm"
        >
            {{ t('Showing cached and queued records from this device.') }}
        </p>

        <div class="rounded-md border">
            <ul class="divide-y">
                <li
                    v-for="item in displayItems"
                    :key="item.client_uuid || item.id"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <span>{{ item.name || item.title }}</span>
                        <Badge v-if="item.pending_local || item.offline_only" variant="outline">
                            {{ t('Pending sync') }}
                        </Badge>
                    </div>
                    <Link
                        v-if="item.id"
                        :href="'/' + 'field-notes/' + item.id + '/edit'"
                        class="text-sm text-primary"
                    >
                        {{ t('Edit') }}
                    </Link>
                </li>
            </ul>
            <div v-if="online" class="px-4 pb-4">
                <MisPagination :pagination="items" />
            </div>
        </div>
    </div>
</template>
