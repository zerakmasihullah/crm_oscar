<script setup lang="ts">
import { History } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCalendar } from '@/composables/useCalendar';
import { useTranslations } from '@/composables/useTranslations';

export interface StatusChangeLogRecord {
    id: number;
    from_status: string | null;
    to_status: string;
    created_at: string;
    changed_by?: { id: number; name: string } | null;
}

defineProps<{
    logs: StatusChangeLogRecord[];
    title?: string;
    emptyMessage?: string;
}>();

const { t } = useTranslations();
const calendar = useCalendar();

const formatDateTime = (value: string): string =>
    calendar.formatDateTime(value);

const statusLabel = (status: string | null): string => {
    if (!status) {
        return t('Initial');
    }

    const labels: Record<string, string> = {
        active: t('Active'),
        inactive: t('Inactive'),
        disabled: t('Disabled'),
    };

    return labels[status] ?? status;
};

const statusVariant = (
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (!status) {
        return 'outline';
    }

    if (status === 'active') {
        return 'default';
    }

    if (status === 'disabled' || status === 'inactive') {
        return 'destructive';
    }

    return 'secondary';
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <History class="size-5" />
                {{ title ?? t('Status History') }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="!logs.length" class="text-sm text-muted-foreground">
                {{ emptyMessage ?? t('No status changes recorded yet.') }}
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="log in logs"
                    :key="log.id"
                    class="flex flex-col gap-2 rounded-md border px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :variant="statusVariant(log.from_status)">
                            {{ statusLabel(log.from_status) }}
                        </Badge>
                        <span class="text-muted-foreground">→</span>
                        <Badge :variant="statusVariant(log.to_status)">
                            {{ statusLabel(log.to_status) }}
                        </Badge>
                    </div>
                    <div class="text-muted-foreground">
                        <span>{{ formatDateTime(log.created_at) }}</span>
                        <span v-if="log.changed_by">
                            · {{ log.changed_by.name }}</span
                        >
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
