<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, useSlots } from 'vue';
import FileUploadField from '@/components/FileUploadField.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { getOfflineSettings } from '@/offline/settings';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title?: string;
        offlineEnabled?: boolean;
        online?: boolean;
        processing?: boolean;
        clientUuid: string;
        resource: string;
        showAttachments?: boolean;
        submitLabel?: string;
        cancelHref?: string;
        cancelLabel?: string;
        class?: string;
        maxWidth?: 'md' | 'lg' | 'xl' | '2xl' | 'full';
    }>(),
    {
        title: undefined,
        offlineEnabled: false,
        online: true,
        processing: false,
        showAttachments: true,
        submitLabel: undefined,
        cancelHref: undefined,
        cancelLabel: undefined,
        class: undefined,
        maxWidth: '2xl',
    },
);

const emit = defineEmits<{
    submit: [];
    'attachment-stored': [id: string, name: string];
}>();

const { t } = useTranslations();
const slots = useSlots();
const settings = computed(() => getOfflineSettings());

const attachmentsVisible = computed(
    () =>
        props.showAttachments
        && props.offlineEnabled
        && settings.value.attachments_enabled
        && ! props.online,
);

const maxWidthClass = computed(() => {
    const map = {
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-3xl',
        '2xl': 'max-w-5xl',
        full: 'max-w-none',
    } as const;
    return map[props.maxWidth];
});

function onOfflineComplete(payload: { offlineBlobId?: string | null; item: { file: File } }): void {
    if (payload.offlineBlobId) {
        emit('attachment-stored', payload.offlineBlobId, payload.item.file.name);
    }
}
</script>

<template>
    <form
        :class="
            cn(
                'mx-auto flex w-full flex-col gap-6',
                maxWidthClass,
                props.class,
            )
        "
        @submit.prevent="emit('submit')"
    >
        <div
            v-if="title || offlineEnabled || slots.description"
            class="space-y-1.5"
        >
            <h1
                v-if="title"
                class="text-2xl font-semibold tracking-tight"
            >
                {{ title }}
            </h1>
            <p v-if="offlineEnabled" class="text-sm text-muted-foreground">
                {{
                    online
                        ? t('Online — submits immediately.')
                        : t('Offline — queues to outbox.')
                }}
            </p>
            <slot name="description" />
        </div>

        <div class="grid gap-5">
            <slot />
        </div>

        <FileUploadField
            v-if="attachmentsVisible"
            mode="offline"
            :client-uuid="clientUuid"
            :resource="resource"
            :max-bytes="settings.attachment_max_bytes"
            :accept="settings.allowed_mimes.join(',')"
            :label="t('Attachment')"
            :multiple="false"
            @complete="onOfflineComplete"
        />

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border/50 pt-5">
            <slot name="actions">
                <Button
                    v-if="cancelHref"
                    as-child
                    type="button"
                    variant="outline"
                    size="lg"
                    :disabled="processing"
                >
                    <Link :href="cancelHref">
                        {{ cancelLabel || t('Cancel') }}
                    </Link>
                </Button>
                <Button type="submit" size="lg" :disabled="processing">
                    {{ submitLabel || t('Save') }}
                </Button>
            </slot>
        </div>

        <slot v-if="slots.footer" name="footer" />
    </form>
</template>
