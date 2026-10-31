<script setup lang="ts">
import { FileIcon, FileText, ImageIcon, Trash2, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FileUploadProgress from '@/components/FileUploadProgress.vue';
import MisField from '@/components/mis/MisField.vue';
import { Button } from '@/components/ui/button';
import {
    createUploadItem,
    fetchChunkedUploadConfig,
    uploadFileChunked,
    type ChunkedUploadConfig,
    type ChunkedUploadItem,
} from '@/composables/useChunkedUpload';
import { useTranslations } from '@/composables/useTranslations';
import { storeAttachmentBlob } from '@/offline/outbox';
import { getOfflineSettings } from '@/offline/settings';
import { cn } from '@/lib/utils';

export type ExistingUploadFile = {
    id?: number | string;
    name: string;
    size?: number | null;
    mime?: string | null;
    url?: string | null;
    path?: string | null;
};

export type FileUploadCompletePayload = {
    item: ChunkedUploadItem;
    path?: string | null;
    attachmentId?: number | null;
    downloadUrl?: string | null;
    deferredFile?: File | null;
    offlineBlobId?: string | null;
};

const props = withDefaults(
    defineProps<{
        /** chunked = immediate resumable upload; deferred = File for parent form; offline = IndexedDB */
        mode?: 'chunked' | 'deferred' | 'offline';
        destination?: 'file_manager' | 'attachment' | 'disk';
        root?: 'public' | 'private';
        path?: string;
        disk?: string;
        directory?: string;
        attachable?: string;
        attachableId?: number | string | null;
        /** When set with single file, new upload replaces this attachment/path and deletes the old one. */
        replaceAttachmentId?: number | null;
        replacePath?: string | null;
        existing?: ExistingUploadFile | ExistingUploadFile[] | null;
        multiple?: boolean;
        maxFiles?: number;
        maxBytes?: number | null;
        accept?: string;
        label?: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        preview?: 'none' | 'image' | 'list';
        concurrency?: number;
        chunkBytes?: number;
        computeFileChecksum?: boolean;
        /** Hide the built-in choose button (use triggerPicker from parent). */
        hidePicker?: boolean;
        /** Offline mode */
        clientUuid?: string;
        resource?: string;
        class?: string;
    }>(),
    {
        mode: 'chunked',
        destination: 'file_manager',
        root: 'private',
        path: '',
        disk: 'public',
        directory: '',
        attachable: undefined,
        attachableId: null,
        replaceAttachmentId: null,
        replacePath: null,
        existing: null,
        multiple: false,
        maxFiles: 5,
        maxBytes: null,
        accept: undefined,
        label: undefined,
        hint: undefined,
        error: undefined,
        disabled: false,
        required: false,
        preview: 'list',
        concurrency: undefined,
        chunkBytes: undefined,
        computeFileChecksum: false,
        hidePicker: false,
        clientUuid: undefined,
        resource: undefined,
        class: undefined,
    },
);

const emit = defineEmits<{
    complete: [payload: FileUploadCompletePayload];
    remove: [file: ExistingUploadFile];
    error: [message: string];
    'update:deferred': [files: File[]];
    progress: [items: ChunkedUploadItem[]];
}>();

const { t } = useTranslations();
const inputRef = ref<HTMLInputElement | null>(null);
const uploadItems = ref<ChunkedUploadItem[]>([]);
const uploadControllers = ref<Map<string, AbortController>>(new Map());
const localError = ref<string | null>(null);
const config = ref<ChunkedUploadConfig | null>(null);
const deferredFiles = ref<File[]>([]);
const offlineFiles = ref<Array<{ id: string; name: string; size?: number }>>([]);
const existingFiles = ref<ExistingUploadFile[]>([]);
const imagePreviewUrl = ref<string | null>(null);
const dragActive = ref(false);
let dragDepth = 0;

const canAddMore = computed(() => {
    if (!props.multiple) {
        return true;
    }
    const used =
        existingFiles.value.length
        + deferredFiles.value.length
        + offlineFiles.value.length;
    return used < props.maxFiles;
});

const listedFiles = computed(() => {
    const rows: Array<{
        key: string;
        name: string;
        size?: number | null;
        url?: string | null;
        kind: 'existing' | 'deferred' | 'offline';
        source: ExistingUploadFile | File | { id: string; name: string; size?: number };
    }> = [];

    for (const [index, file] of existingFiles.value.entries()) {
        rows.push({
            key: `existing-${String(file.id ?? file.path ?? file.name)}-${index}`,
            name: file.name,
            size: file.size,
            url: file.url,
            kind: 'existing',
            source: file,
        });
    }

    for (const [index, file] of deferredFiles.value.entries()) {
        rows.push({
            key: `deferred-${file.name}-${file.size}-${index}`,
            name: file.name,
            size: file.size,
            kind: 'deferred',
            source: file,
        });
    }

    for (const file of offlineFiles.value) {
        rows.push({
            key: `offline-${file.id}`,
            name: file.name,
            size: file.size,
            kind: 'offline',
            source: file,
        });
    }

    return rows;
});

watch(
    () => props.existing,
    (value) => {
        if (!value) {
            existingFiles.value = [];
            return;
        }

        existingFiles.value = Array.isArray(value) ? [...value] : [value];
    },
    { immediate: true, deep: true },
);

watch(
    existingFiles,
    (files) => {
        const first = files[0];

        if (props.preview === 'image' && first?.url) {
            imagePreviewUrl.value = first.url;
        } else if (props.preview === 'image' && !deferredFiles.value[0]) {
            imagePreviewUrl.value = null;
        }
    },
    { immediate: true, deep: true },
);

const isUploading = computed(() =>
    uploadItems.value.some((item) =>
        ['queued', 'hashing', 'uploading', 'assembling'].includes(item.status),
    ),
);

const effectiveMaxBytes = computed(() => {
    if (props.maxBytes != null) {
        return props.maxBytes;
    }

    if (props.mode === 'offline') {
        return getOfflineSettings().attachment_max_bytes;
    }

    return config.value?.max_bytes ?? 5 * 1024 * 1024 * 1024;
});

const effectiveAccept = computed(() => {
    if (props.accept) {
        return props.accept;
    }

    if (props.mode === 'offline') {
        return getOfflineSettings().allowed_mimes.join(',');
    }

    return undefined;
});

const displayError = computed(() => props.error || localError.value);

const hintText = computed(() => {
    if (props.hint) {
        return props.hint;
    }

    return t('Max :max', { max: formatBytes(effectiveMaxBytes.value) });
});

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

function triggerPicker(): void {
    if (props.disabled || isUploading.value) {
        return;
    }

    inputRef.value?.click();
}

function patchUploadItem(item: ChunkedUploadItem): void {
    const index = uploadItems.value.findIndex((entry) => entry.id === item.id);

    if (index === -1) {
        uploadItems.value.push({ ...item });
    } else {
        uploadItems.value[index] = { ...item };
    }

    emit('progress', [...uploadItems.value]);
}

async function ensureConfig(): Promise<void> {
    if (config.value || props.mode !== 'chunked') {
        return;
    }

    config.value = await fetchChunkedUploadConfig();
}

async function ingestFiles(files: File[]): Promise<void> {
    if (files.length === 0) {
        return;
    }

    localError.value = null;

    try {
        await ensureConfig();
    } catch (error) {
        const message = error instanceof Error ? error.message : t('Could not load upload settings.');
        localError.value = message;
        emit('error', message);
        return;
    }

    const selected = props.multiple ? files : files.slice(0, 1);
    const remainingSlots = props.multiple
        ? Math.max(0, props.maxFiles - existingFiles.value.length - deferredFiles.value.length - offlineFiles.value.length)
        : 1;

    if (props.multiple && selected.length > remainingSlots) {
        localError.value = t('You can upload at most :n file(s).', { n: String(props.maxFiles) });
        return;
    }

    for (const file of selected) {
        if (file.size > effectiveMaxBytes.value) {
            localError.value = t('":name" exceeds the maximum upload size of :max.', {
                name: file.name,
                max: formatBytes(effectiveMaxBytes.value),
            });
            emit('error', localError.value);
            return;
        }
    }

    if (props.mode === 'deferred') {
        await handleDeferred(selected);
        return;
    }

    if (props.mode === 'offline') {
        await handleOffline(selected);
        return;
    }

    await handleChunked(selected);
}

async function onFilesSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);

    if (inputRef.value) {
        inputRef.value.value = '';
    }

    await ingestFiles(files);
}

function onDragEnter(event: DragEvent): void {
    if (props.disabled || isUploading.value || props.hidePicker) {
        return;
    }
    event.preventDefault();
    dragDepth += 1;
    dragActive.value = true;
}

function onDragOver(event: DragEvent): void {
    if (props.disabled || isUploading.value || props.hidePicker) {
        return;
    }
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
    dragActive.value = true;
}

function onDragLeave(event: DragEvent): void {
    event.preventDefault();
    dragDepth = Math.max(0, dragDepth - 1);
    if (dragDepth === 0) {
        dragActive.value = false;
    }
}

async function onDrop(event: DragEvent): Promise<void> {
    event.preventDefault();
    dragDepth = 0;
    dragActive.value = false;

    if (props.disabled || isUploading.value || props.hidePicker) {
        return;
    }

    const files = Array.from(event.dataTransfer?.files ?? []);
    await ingestFiles(files);
}

function isImageName(name: string): boolean {
    return /\.(png|jpe?g|webp|gif|bmp|svg)$/i.test(name);
}

async function handleDeferred(files: File[]): Promise<void> {
    if (!props.multiple) {
        // Replacing deferred selection — mark existing for removal.
        if (existingFiles.value.length > 0) {
            for (const file of [...existingFiles.value]) {
                emit('remove', file);
            }
            existingFiles.value = [];
        }

        deferredFiles.value = files.slice(0, 1);
    } else {
        deferredFiles.value = [...deferredFiles.value, ...files].slice(0, props.maxFiles);
    }

    const file = deferredFiles.value[0];

    if (props.preview === 'image' && file) {
        if (imagePreviewUrl.value?.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }

        imagePreviewUrl.value = URL.createObjectURL(file);
    }

    emit('update:deferred', [...deferredFiles.value]);

    for (const deferred of deferredFiles.value) {
        emit('complete', {
            item: createUploadItem(deferred),
            deferredFile: deferred,
        });
    }
}

async function handleOffline(files: File[]): Promise<void> {
    if (!props.clientUuid || !props.resource) {
        localError.value = t('Offline upload is not configured.');
        return;
    }

    for (const file of files) {
        try {
            const id = await storeAttachmentBlob({
                clientUuid: props.clientUuid,
                resource: props.resource,
                file,
                maxBytes: effectiveMaxBytes.value,
                allowedMimes: effectiveAccept.value?.split(',').filter(Boolean),
            });
            offlineFiles.value.push({ id, name: file.name, size: file.size });
            emit('complete', {
                item: createUploadItem(file),
                offlineBlobId: id,
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : t('Upload failed');
            localError.value = message;
            emit('error', message);
        }
    }
}

async function handleChunked(files: File[]): Promise<void> {
    const existingToReplace =
        !props.multiple && existingFiles.value.length === 1 ? existingFiles.value[0] : null;

    const replaceAttachmentId =
        !props.multiple
            ? (props.replaceAttachmentId
                ?? (existingToReplace?.id != null ? Number(existingToReplace.id) : null))
            : null;

    const replacePath =
        !props.multiple
            ? (props.replacePath ?? existingToReplace?.path ?? null)
            : null;

    // Optimistic UI clear for single replace; server deletes old file on complete.
    if (!props.multiple && existingFiles.value.length > 0) {
        existingFiles.value = [];
    }

    for (const file of files) {
        const item = createUploadItem(file);
        patchUploadItem(item);

        const controller = new AbortController();
        uploadControllers.value.set(item.id, controller);

        const result = await uploadFileChunked(item, {
            destination: props.destination,
            root: props.root,
            path: props.path,
            disk: props.disk,
            directory: props.directory,
            attachable: props.attachable,
            attachableId: props.attachableId ?? undefined,
            replaceAttachmentId,
            replacePath,
            concurrency: props.concurrency ?? config.value?.concurrency ?? 3,
            chunkBytes: props.chunkBytes ?? config.value?.chunk_bytes,
            computeFileChecksum: props.computeFileChecksum || (config.value?.require_file_checksum ?? false),
            signal: controller.signal,
            onItemUpdate: patchUploadItem,
        });

        uploadControllers.value.delete(item.id);

        if (result.status === 'completed') {
            const existing: ExistingUploadFile = {
                id: result.attachmentId ?? undefined,
                name: result.file.name,
                size: result.totalBytes,
                url: result.downloadUrl,
                path: result.finalPath,
            };

            if (props.multiple) {
                existingFiles.value.push(existing);
            } else {
                existingFiles.value = [existing];
            }

            if (props.preview === 'image' && (result.downloadUrl || imagePreviewUrl.value)) {
                if (result.downloadUrl) {
                    imagePreviewUrl.value = result.downloadUrl;
                }
            }

            if (existingToReplace) {
                emit('remove', existingToReplace);
            }

            emit('complete', {
                item: result,
                path: result.finalPath,
                attachmentId: result.attachmentId,
                downloadUrl: result.downloadUrl,
            });
        } else if (result.status === 'failed' && result.error) {
            if (existingToReplace) {
                existingFiles.value = [existingToReplace];
            }

            localError.value = result.error;
            emit('error', result.error);
        }
    }
}

function cancelUpload(id: string): void {
    uploadControllers.value.get(id)?.abort();
}

function dismissUpload(id: string): void {
    cancelUpload(id);
    uploadItems.value = uploadItems.value.filter((item) => item.id !== id);
    emit('progress', [...uploadItems.value]);
}

function clearFinishedUploads(): void {
    uploadItems.value = uploadItems.value.filter((item) =>
        ['queued', 'hashing', 'uploading', 'assembling'].includes(item.status),
    );
    emit('progress', [...uploadItems.value]);
}

async function removeListed(row: (typeof listedFiles.value)[number]): Promise<void> {
    if (props.disabled) {
        return;
    }

    if (row.kind === 'deferred') {
        const file = row.source as File;
        deferredFiles.value = deferredFiles.value.filter((entry) => entry !== file);
        emit('update:deferred', [...deferredFiles.value]);

        if (props.preview === 'image' && deferredFiles.value[0]) {
            if (imagePreviewUrl.value?.startsWith('blob:')) {
                URL.revokeObjectURL(imagePreviewUrl.value);
            }
            imagePreviewUrl.value = URL.createObjectURL(deferredFiles.value[0]);
        } else if (props.preview === 'image' && imagePreviewUrl.value?.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
            imagePreviewUrl.value = null;
        }
        return;
    }

    if (row.kind === 'offline') {
        const file = row.source as { id: string; name: string; size?: number };
        offlineFiles.value = offlineFiles.value.filter((entry) => entry.id !== file.id);
        return;
    }

    const file = row.source as ExistingUploadFile;

    if (file.id != null && props.mode === 'chunked' && props.destination === 'attachment') {
        try {
            const response = await fetch(`/attachments/${file.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
                        '',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(t('Could not delete the file.'));
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : t('Could not delete the file.');
            localError.value = message;
            emit('error', message);
            return;
        }
    }

    existingFiles.value = existingFiles.value.filter((entry) => entry !== file);

    if (props.mode === 'deferred' && !props.multiple) {
        deferredFiles.value = [];
        emit('update:deferred', []);

        if (imagePreviewUrl.value?.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }

        imagePreviewUrl.value = null;
    }

    emit('remove', file);
}

defineExpose({
    isUploading,
    deferredFiles,
    uploadItems,
    triggerPicker,
    clearFinishedUploads,
});
</script>

<template>
    <component
        :is="label || error || hintText ? MisField : 'div'"
        :label="label"
        :hint="undefined"
        :error="displayError ?? undefined"
        :required="required"
        :class="cn('w-full', props.class)"
    >
        <div class="space-y-3">
            <div
                v-if="preview === 'image' && imagePreviewUrl"
                class="overflow-hidden rounded-2xl border border-border/70 bg-muted/20"
            >
                <img
                    :src="imagePreviewUrl"
                    alt=""
                    class="mx-auto max-h-56 object-contain p-4"
                />
            </div>

            <button
                v-if="!hidePicker && canAddMore"
                type="button"
                :disabled="disabled || isUploading"
                :class="
                    cn(
                        'group relative flex w-full flex-col items-center justify-center gap-3 rounded-2xl border border-dashed px-6 py-10 text-center transition-colors',
                        'bg-muted/15 hover:bg-muted/30 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40',
                        dragActive
                            ? 'border-primary bg-primary/5'
                            : displayError
                              ? 'border-destructive/50'
                              : 'border-border/80',
                        (disabled || isUploading) && 'pointer-events-none opacity-60',
                    )
                "
                @click="triggerPicker"
                @dragenter="onDragEnter"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-2xl bg-background shadow-xs ring-1 ring-border/70 transition-transform duration-200 group-hover:scale-105"
                >
                    <Upload class="size-5 text-muted-foreground" />
                </span>
                <span class="space-y-1">
                    <span class="block text-sm font-medium">
                        {{
                            isUploading
                                ? t('Uploading…')
                                : listedFiles.length && !multiple
                                  ? t('Replace file')
                                  : t('Drop files here or click to browse')
                        }}
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ hintText }}
                    </span>
                </span>
            </button>

            <div
                v-if="preview === 'list' && listedFiles.length > 0"
                class="grid gap-2 sm:grid-cols-2"
            >
                <div
                    v-for="file in listedFiles"
                    :key="file.key"
                    class="flex items-center gap-3 rounded-xl border border-border/70 bg-card px-3 py-2.5 shadow-xs"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted/60 text-muted-foreground"
                    >
                        <ImageIcon
                            v-if="isImageName(file.name)"
                            class="size-4"
                        />
                        <FileText
                            v-else-if="/\.pdf$/i.test(file.name)"
                            class="size-4"
                        />
                        <FileIcon v-else class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <a
                            v-if="file.url"
                            :href="file.url"
                            class="block truncate text-sm font-medium text-primary underline-offset-2 hover:underline"
                            target="_blank"
                            rel="noopener"
                        >
                            {{ file.name }}
                        </a>
                        <p v-else class="truncate text-sm font-medium">
                            {{ file.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="file.size">{{ formatBytes(file.size) }}</span>
                            <span v-if="file.kind !== 'existing'">
                                <span v-if="file.size"> · </span>
                                {{ t('Ready to upload') }}
                            </span>
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                        :disabled="disabled || isUploading"
                        @click="removeListed(file)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <input
                ref="inputRef"
                type="file"
                class="hidden"
                :accept="effectiveAccept"
                :multiple="multiple"
                :disabled="disabled || isUploading"
                @change="onFilesSelected"
            />

            <FileUploadProgress
                :items="uploadItems"
                @cancel="cancelUpload"
                @dismiss="dismissUpload"
                @clear-finished="clearFinishedUploads"
            />
        </div>
    </component>
</template>
