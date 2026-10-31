import { usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useTranslations } from '@/composables/useTranslations';
import { getOfflineSettings } from '@/offline/settings';
import { useOfflineForm } from '@/offline/useOfflineForm';

type OfflineResourceFormOptions = {
    resource: string;
    offlineEnabled?: boolean;
    action?: 'create' | 'update' | 'upsert';
    initialClientUuid?: string;
    initialValues: Record<string, unknown>;
    onlineSubmit: (payload: Record<string, unknown>) => void | Promise<void>;
    /** Extra fields merged into the offline/online payload (e.g. existing attachments). */
    extraPayload?: () => Record<string, unknown>;
    onQueued?: () => void;
    resetLocalOnQueue?: boolean;
};

/**
 * Standard offline-capable form state for any resource page.
 * Prefer this over hand-rolling useOfflineForm + attachment bookkeeping.
 */
export function useOfflineResourceForm(options: OfflineResourceFormOptions) {
    const { t } = useTranslations();
    const page = usePage();
    const settings = computed(() => getOfflineSettings());
    const attachmentIds = reactive<string[]>([]);

    const offlineEnabled = computed(() => {
        if (typeof options.offlineEnabled === 'boolean') {
            return options.offlineEnabled;
        }

        return Boolean(
            (page.props as { offlineEnabled?: boolean }).offlineEnabled
                ?? settings.value.enabled,
        );
    });

    const clientUuid = ref(
        options.initialClientUuid
            ?? (typeof options.initialValues.client_uuid === 'string'
                ? options.initialValues.client_uuid
                : crypto.randomUUID()),
    );

    const local = reactive({ ...options.initialValues }) as Record<string, unknown>;

    const { processing, queued, submit, online, error } = useOfflineForm({
        resource: options.resource,
        offlineEnabled,
        action: options.action ?? 'upsert',
        initialClientUuid: clientUuid.value,
        onlineSubmit: options.onlineSubmit,
    });

    async function onSubmit(): Promise<void> {
        const extras = options.extraPayload?.() ?? {};
        const queuedAttachments = attachmentIds.map((id) => ({ id }));
        const existingMeta = Array.isArray(extras.attachments_meta)
            ? (extras.attachments_meta as Array<{ id: string | number }>)
            : [];

        await submit({
            ...local,
            client_uuid: clientUuid.value,
            ...extras,
            ...(queuedAttachments.length > 0 || existingMeta.length > 0
                ? { attachments_meta: [...existingMeta, ...queuedAttachments] }
                : {}),
        });

        if (queued.value) {
            toast.success(t('Saved offline. Will sync when online.'));

            if (options.resetLocalOnQueue !== false) {
                for (const key of Object.keys(options.initialValues)) {
                    if (key === 'client_uuid') {
                        continue;
                    }

                    local[key] = options.initialValues[key];
                }

                attachmentIds.splice(0, attachmentIds.length);
                clientUuid.value = crypto.randomUUID();
            }

            options.onQueued?.();
        }
    }

    function onAttachmentStored(id: string): void {
        attachmentIds.push(id);
    }

    return {
        local,
        clientUuid,
        attachmentIds,
        settings,
        offlineEnabled,
        online,
        processing,
        queued,
        error,
        attachmentsEnabled: computed(
            () => offlineEnabled.value && settings.value.attachments_enabled && ! online.value,
        ),
        onSubmit,
        onAttachmentStored,
    };
}
