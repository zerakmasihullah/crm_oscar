<script setup lang="ts">
import FileUploadField from '@/components/FileUploadField.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    clientUuid: string;
    resource: string;
    enabled?: boolean;
    maxBytes?: number;
    allowedMimes?: string[];
}>();

const emit = defineEmits<{
    stored: [id: string, name: string];
}>();

const { t } = useTranslations();

function onComplete(payload: { offlineBlobId?: string | null; item: { file: File } }): void {
    if (payload.offlineBlobId) {
        emit('stored', payload.offlineBlobId, payload.item.file.name);
    }
}
</script>

<template>
    <FileUploadField
        v-if="enabled !== false"
        mode="offline"
        :client-uuid="clientUuid"
        :resource="resource"
        :max-bytes="maxBytes"
        :accept="allowedMimes?.join(',')"
        :label="t('Attachment')"
        :multiple="false"
        @complete="onComplete"
    />
</template>
