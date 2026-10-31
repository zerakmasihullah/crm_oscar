<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import OfflineResourceForm from '@/components/OfflineResourceForm.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { useOfflineResourceForm } from '@/offline/useOfflineResourceForm';

const props = defineProps<{
    item?: { id: number; name: string; client_uuid?: string };
    offlineEnabled?: boolean;
}>();

const { t } = useTranslations();

const form = useForm({
    client_uuid: props.item?.client_uuid ?? crypto.randomUUID(),
    name: props.item?.name ?? '',
});

const {
    local,
    clientUuid,
    offlineEnabled,
    online,
    processing,
    attachmentsEnabled,
    onSubmit,
    onAttachmentStored,
} = useOfflineResourceForm({
    resource: 'field_notes',
    offlineEnabled: Boolean(props.offlineEnabled),
    initialClientUuid: form.client_uuid,
    initialValues: {
        name: props.item?.name ?? '',
    },
    resetLocalOnQueue: true,
    onlineSubmit: async (payload) => {
        form.client_uuid = String(payload.client_uuid);
        form.name = String(payload.name ?? '');
        form.post('/field-notes');
    },
});
</script>

<template>
    <Head :title="t('Create FieldNote')" />

    <OfflineResourceForm
        :title="t('Create FieldNote')"
        :offline-enabled="offlineEnabled"
        :online="online"
        :processing="processing || form.processing"
        :client-uuid="clientUuid"
        resource="field_notes"
        :show-attachments="attachmentsEnabled"
        @submit="onSubmit"
        @attachment-stored="onAttachmentStored"
    >
        <div class="space-y-2">
            <Label for="name">{{ t('Name') }}</Label>
            <Input id="name" v-model="local.name" required />
        </div>
    </OfflineResourceForm>
</template>
