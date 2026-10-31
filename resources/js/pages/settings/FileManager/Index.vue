<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronRight,
    Download,
    Eye,
    File,
    FileImage,
    FileText,
    Folder,
    FolderOpen,
    FolderPlus,
    HardDrive,
    Home,
    Move,
    Pencil,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Can from '@/components/Can.vue';
import FileUploadField from '@/components/FileUploadField.vue';
import InputError from '@/components/InputError.vue';
import { MisSearchInput } from '@/components/mis';
import RowActionsMenu from '@/components/RowActionsMenu.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCalendar } from '@/composables/useCalendar';
import { useTranslations } from '@/composables/useTranslations';
import type { RowActionItem } from '@/lib/row-actions';
import StorageSettingsTabs from '@/components/settings/StorageSettingsTabs.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';

type StorageRoot = {
    key: string;
    label: string;
    description: string;
    exists: boolean;
    file_count: number;
    folder_count: number;
    total_bytes: number;
};

type FileEntry = {
    name: string;
    path: string;
    type: 'file' | 'folder';
    extension: string | null;
    size: number | null;
    mime: string | null;
    modified_at: string;
    items: number | null;
};

type Listing = {
    root: string;
    path: string;
    breadcrumbs: { label: string; path: string }[];
    entries: FileEntry[];
    parent_path: string | null;
    summary: {
        folders: number;
        files: number;
        total_bytes: number;
    };
};

type Preview = {
    name: string;
    path: string;
    type: string;
    extension: string | null;
    size: number;
    mime: string | null;
    modified_at: string;
    is_text: boolean;
    is_image: boolean;
    previewable: boolean;
    content: string | null;
    truncated: boolean;
    public_url: string | null;
};

const props = defineProps<{
    roots: StorageRoot[];
    listing: Listing;
    preview: Preview | null;
    filters: {
        root: string;
        path: string;
        search?: string | null;
        preview?: string | null;
    };
    permissions: {
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
    limits: {
        max_upload_bytes: number;
        simple_max_upload_bytes?: number;
        chunk_bytes?: number;
        concurrency?: number;
        require_chunk_checksum?: boolean;
        require_file_checksum?: boolean;
        max_preview_bytes: number;
    };
}>();

const { t } = useTranslations();
const calendar = useCalendar();

const search = ref(props.filters.search ?? '');
const folderDialogOpen = ref(false);
const renameDialogOpen = ref(false);
const moveDialogOpen = ref(false);
const selectedEntry = ref<FileEntry | null>(null);
const uploadError = ref<string | null>(null);
const fileUpload = ref<InstanceType<typeof FileUploadField> | null>(null);

const folderForm = useForm({
    root: props.listing.root,
    path: props.listing.path,
    name: '',
});

const renameForm = useForm({
    root: props.listing.root,
    path: '',
    name: '',
    current_path: props.listing.path,
});

const moveForm = useForm({
    root: props.listing.root,
    path: '',
    destination: '',
    current_path: props.listing.path,
});

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search ?? '';
        folderForm.root = filters.root;
        folderForm.path = filters.path;
        renameForm.root = filters.root;
        renameForm.current_path = filters.path;
        moveForm.root = filters.root;
        moveForm.current_path = filters.path;
    },
);

const activeRoot = computed(
    () => props.roots.find((root) => root.key === props.listing.root) ?? props.roots[0],
);

const folderDestinations = computed(() => {
    const options = [{ label: t('Root of this storage'), value: '' }];

    if (props.listing.parent_path !== null) {
        options.push({
            label: t('Parent folder'),
            value: props.listing.parent_path,
        });
    }

    for (const entry of props.listing.entries) {
        if (entry.type !== 'folder') {
            continue;
        }

        if (selectedEntry.value?.path === entry.path) {
            continue;
        }

        options.push({ label: entry.name, value: entry.path });
    }

    return options;
});

function browse(options: {
    root?: string;
    path?: string;
    search?: string;
    preview?: string | null;
}): void {
    router.get(
        '/settings/file-manager',
        {
            root: options.root ?? props.listing.root,
            path: options.path ?? '',
            search: options.search || undefined,
            preview: options.preview || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function switchRoot(root: string): void {
    search.value = '';
    browse({ root, path: '', search: '' });
}

function openFolder(path: string): void {
    search.value = '';
    browse({ path, search: '' });
}

function applySearch(): void {
    browse({
        path: props.listing.path,
        search: search.value,
        preview: props.preview?.path ?? null,
    });
}

function clearSearch(): void {
    search.value = '';
    browse({ path: props.listing.path, search: '' });
}

function openPreview(entry: FileEntry): void {
    if (entry.type === 'folder') {
        openFolder(entry.path);
        return;
    }

    browse({
        path: props.listing.path,
        search: search.value,
        preview: entry.path,
    });
}

function closePreview(): void {
    browse({
        path: props.listing.path,
        search: search.value,
        preview: null,
    });
}

function downloadUrl(path: string): string {
    const params = new URLSearchParams({
        root: props.listing.root,
        path,
    });

    return `/settings/file-manager/download?${params.toString()}`;
}

function previewMediaUrl(path: string): string {
    const params = new URLSearchParams({
        root: props.listing.root,
        path,
    });

    return `/settings/file-manager/preview?${params.toString()}`;
}

function openCreateFolder(): void {
    folderForm.name = '';
    folderForm.clearErrors();
    folderDialogOpen.value = true;
}

function submitCreateFolder(): void {
    folderForm.root = props.listing.root;
    folderForm.path = props.listing.path;
    folderForm.post('/settings/file-manager/folders', {
        preserveScroll: true,
        onSuccess: () => {
            folderDialogOpen.value = false;
            folderForm.reset('name');
        },
    });
}

function triggerUpload(): void {
    fileUpload.value?.triggerPicker();
}

function onUploadComplete(): void {
    uploadError.value = null;
    router.reload({
        onSuccess: () => undefined,
    });
}

function onUploadError(message: string): void {
    uploadError.value = message;
}

function openRename(entry: FileEntry): void {
    selectedEntry.value = entry;
    renameForm.path = entry.path;
    renameForm.name = entry.name;
    renameForm.current_path = props.listing.path;
    renameForm.root = props.listing.root;
    renameForm.clearErrors();
    renameDialogOpen.value = true;
}

function submitRename(): void {
    renameForm.put('/settings/file-manager/rename', {
        preserveScroll: true,
        onSuccess: () => {
            renameDialogOpen.value = false;
            selectedEntry.value = null;
        },
    });
}

function openMove(entry: FileEntry): void {
    selectedEntry.value = entry;
    moveForm.path = entry.path;
    moveForm.destination = '';
    moveForm.current_path = props.listing.path;
    moveForm.root = props.listing.root;
    moveForm.clearErrors();
    moveDialogOpen.value = true;
}

function submitMove(): void {
    moveForm.put('/settings/file-manager/move', {
        preserveScroll: true,
        onSuccess: () => {
            moveDialogOpen.value = false;
            selectedEntry.value = null;
        },
    });
}

function deleteEntry(entry: FileEntry): void {
    router.delete('/settings/file-manager', {
        data: {
            root: props.listing.root,
            path: entry.path,
            current_path: props.listing.path,
        },
        preserveScroll: true,
    });
}

function entryActions(entry: FileEntry): RowActionItem[] {
    const actions: RowActionItem[] = [];

    if (entry.type === 'file') {
        actions.push({
            label: t('Preview'),
            icon: Eye,
            onClick: () => openPreview(entry),
        });
        actions.push({
            label: t('Download'),
            icon: Download,
            href: downloadUrl(entry.path),
            download: true,
        });
    } else {
        actions.push({
            label: t('Open'),
            icon: FolderOpen,
            onClick: () => openFolder(entry.path),
        });
    }

    if (props.permissions.edit) {
        actions.push({
            label: t('Rename'),
            icon: Pencil,
            onClick: () => openRename(entry),
            separator: true,
        });
        actions.push({
            label: t('Move'),
            icon: Move,
            onClick: () => openMove(entry),
        });
    }

    if (props.permissions.delete) {
        actions.push({
            label: t('Move to trash'),
            icon: Trash2,
            variant: 'destructive',
            separator: true,
            dust: true,
            confirm: {
                title: t('Move to trash?'),
                description:
                    entry.type === 'folder'
                        ? t('This folder will move to trash. You can restore it later.')
                        : t('This file will move to trash. You can restore it later.'),
                confirmLabel: t('Move to trash'),
            },
            onClick: () => deleteEntry(entry),
        });
    }

    return actions;
}

function formatBytes(bytes: number | null | undefined): string {
    if (bytes == null) {
        return '—';
    }

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

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return calendar.formatDateTime(value) || value;
}

function entryIcon(entry: FileEntry) {
    if (entry.type === 'folder') {
        return Folder;
    }

    if (entry.extension && ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'].includes(entry.extension)) {
        return FileImage;
    }

    if (
        entry.extension &&
        ['txt', 'md', 'json', 'csv', 'log', 'xml', 'yml', 'yaml', 'css', 'js', 'ts', 'vue', 'php', 'html', 'sql'].includes(
            entry.extension,
        )
    ) {
        return FileText;
    }

    return File;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/file-manager' },
            { title: 'File Manager', href: '/settings/file-manager' },
        ],
    },
});
</script>

<template>
    <Head :title="t('File Manager')" />

    <SettingsPage>
        <template #tabs>
            <StorageSettingsTabs />
        </template>


    <div class="space-y-6">
        <SettingsPanel :padded="false" class="overflow-hidden">
            <div
                class="relative border-b border-border/60 bg-gradient-to-br from-muted/50 via-card to-card px-6 py-6 sm:px-8 sm:py-7"
            >
                <div
                    class="pointer-events-none absolute inset-y-0 end-0 w-1/2 bg-[radial-gradient(circle_at_top_right,var(--color-primary)_0%,transparent_55%)] opacity-[0.07]"
                />
                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-xl space-y-2">
                        <div class="flex items-center gap-2 text-primary">
                            <HardDrive class="size-5" />
                            <span class="text-xs font-semibold tracking-[0.14em] uppercase">
                                {{ t('File Manager') }}
                            </span>
                        </div>
                        <h1 class="text-2xl font-semibold tracking-tight sm:text-[1.7rem]">
                            {{ t('Browse and organize storage') }}
                        </h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Can permission="storage.create">
                            <Button variant="outline" size="sm" @click="openCreateFolder">
                                <FolderPlus class="size-4" />
                                {{ t('New folder') }}
                            </Button>
                            <Button
                                size="sm"
                                :disabled="Boolean(fileUpload?.isUploading)"
                                @click="triggerUpload"
                            >
                                <Upload class="size-4" />
                                {{ fileUpload?.isUploading ? t('Uploading…') : t('Upload') }}
                            </Button>
                        </Can>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 border-b border-border/60 p-4 sm:grid-cols-2 sm:p-5">
                <button
                    v-for="root in roots"
                    :key="root.key"
                    type="button"
                    class="rounded-xl border px-4 py-3 text-start transition"
                    :class="
                        listing.root === root.key
                            ? 'border-zinc-900 bg-zinc-900/5 shadow-sm'
                            : 'border-border/70 hover:bg-muted/40'
                    "
                    @click="switchRoot(root.key)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold">{{ t(root.label) }}</p>
                        <Badge variant="outline" class="tabular-nums">
                            {{ root.file_count + root.folder_count }}
                        </Badge>
                    </div>
                </button>
            </div>

            <div class="space-y-4 p-4 sm:p-5">
                <div
                    v-if="uploadError"
                    class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    {{ uploadError }}
                </div>

                <Can permission="storage.create">
                    <FileUploadField
                        ref="fileUpload"
                        mode="chunked"
                        destination="file_manager"
                        :root="listing.root === 'private' ? 'private' : 'public'"
                        :path="listing.path"
                        multiple
                        hide-picker
                        :max-bytes="limits.max_upload_bytes"
                        :concurrency="limits.concurrency"
                        :chunk-bytes="limits.chunk_bytes"
                        :compute-file-checksum="Boolean(limits.require_file_checksum)"
                        :hint="t('Max :max · :chunk chunks · concurrent :n', {
                            max: formatBytes(limits.max_upload_bytes),
                            chunk: formatBytes(limits.chunk_bytes ?? 5 * 1024 * 1024),
                            n: String(limits.concurrency ?? 3),
                        })"
                        preview="none"
                        @complete="onUploadComplete"
                        @error="onUploadError"
                    />
                </Can>

                <div class="flex flex-wrap items-center gap-2">
                    <nav class="flex min-w-0 flex-1 flex-wrap items-center gap-1 text-sm">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            @click="browse({ path: '', search: '' })"
                        >
                            <Home class="size-3.5" />
                            <span class="sr-only">{{ t('Root') }}</span>
                        </button>
                        <template v-for="(crumb, index) in listing.breadcrumbs" :key="crumb.path + index">
                            <ChevronRight class="size-3.5 shrink-0 text-muted-foreground/60" />
                            <button
                                type="button"
                                class="max-w-[10rem] truncate rounded-lg px-2 py-1 transition"
                                :class="
                                    index === listing.breadcrumbs.length - 1 && !filters.search
                                        ? 'bg-muted font-semibold text-foreground'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                                @click="browse({ path: crumb.path, search: '' })"
                            >
                                {{ index === 0 ? t(crumb.label) : crumb.label }}
                            </button>
                        </template>
                    </nav>

                    <form class="flex w-full gap-2 sm:w-auto sm:min-w-[280px]" @submit.prevent="applySearch">
                        <MisSearchInput
                            v-model="search"
                            class="min-w-0 flex-1"
                            :placeholder="t('Search files and folders…')"
                            :aria-label="t('Search')"
                        />
                        <Button type="submit" variant="outline">{{ t('Search') }}</Button>
                        <Button
                            v-if="filters.search"
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="clearSearch"
                        >
                            <X class="size-4" />
                        </Button>
                    </form>
                </div>

                <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    <span>
                        {{ listing.summary.folders }} {{ t('folders') }}
                    </span>
                    <span>·</span>
                    <span>
                        {{ listing.summary.files }} {{ t('files') }}
                    </span>
                    <span>·</span>
                    <span>{{ formatBytes(listing.summary.total_bytes) }}</span>
                    <span v-if="filters.search" class="text-foreground">
                        · {{ t('Searching in') }} {{ t(activeRoot?.label ?? 'storage') }}
                    </span>
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="overflow-hidden rounded-xl border border-border/70">
                        <div
                            v-if="listing.entries.length === 0"
                            class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center"
                        >
                            <FolderOpen class="size-10 text-muted-foreground/50" />
                            <p class="text-sm font-medium">
                                {{
                                    filters.search
                                        ? t('No matching files or folders')
                                        : t('This folder is empty')
                                }}
                            </p>
                            <p class="max-w-sm text-xs text-muted-foreground">
                                {{
                                    filters.search
                                        ? t('Try another search term, or clear the search to browse again.')
                                        : t('Upload a file or create a folder to get started.')
                                }}
                            </p>
                        </div>

                        <div v-else class="divide-y divide-border/60">
                            <div
                                v-for="entry in listing.entries"
                                :key="entry.path"
                                class="group flex items-center gap-3 px-3 py-2.5 transition hover:bg-muted/40 sm:px-4"
                                :class="
                                    preview?.path === entry.path
                                        ? 'bg-zinc-900/5'
                                        : ''
                                "
                            >
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 items-center gap-3 text-start"
                                    @click="openPreview(entry)"
                                    @dblclick="entry.type === 'folder' ? openFolder(entry.path) : openPreview(entry)"
                                >
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                                        :class="
                                            entry.type === 'folder'
                                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        <component :is="entryIcon(entry)" class="size-4" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium">
                                            {{ entry.name }}
                                        </span>
                                        <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-muted-foreground">
                                            <span v-if="entry.type === 'folder'">
                                                {{ entry.items ?? 0 }} {{ t('items') }}
                                            </span>
                                            <span v-else>{{ formatBytes(entry.size) }}</span>
                                            <span>·</span>
                                            <span>{{ formatDateTime(entry.modified_at) }}</span>
                                            <span v-if="filters.search && entry.path.includes('/')">
                                                · {{ entry.path }}
                                            </span>
                                        </span>
                                    </span>
                                </button>
                                <RowActionsMenu :actions="entryActions(entry)" />
                            </div>
                        </div>
                    </div>

                    <aside class="rounded-xl border border-border/70 bg-muted/20">
                        <div
                            v-if="!preview"
                            class="flex h-full min-h-[280px] flex-col items-center justify-center gap-2 px-6 py-10 text-center"
                        >
                            <Eye class="size-8 text-muted-foreground/40" />
                            <p class="text-sm font-medium">{{ t('File preview') }}</p>
                        </div>

                        <div v-else class="flex h-full flex-col">
                            <div class="flex items-start justify-between gap-2 border-b border-border/60 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ preview.name }}</p>
                                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                                        {{ formatBytes(preview.size) }}
                                        <span v-if="preview.mime"> · {{ preview.mime }}</span>
                                    </p>
                                </div>
                                <Button variant="ghost" size="icon" @click="closePreview">
                                    <X class="size-4" />
                                </Button>
                            </div>

                            <div class="min-h-0 flex-1 space-y-3 overflow-auto p-4">
                                <div v-if="preview.is_image" class="overflow-hidden rounded-lg border bg-background">
                                    <img
                                        :src="preview.public_url || previewMediaUrl(preview.path)"
                                        :alt="preview.name"
                                        class="mx-auto max-h-72 object-contain"
                                    />
                                </div>

                                <div v-else-if="preview.is_text">
                                    <p
                                        v-if="preview.truncated"
                                        class="mb-2 text-[11px] text-amber-700 dark:text-amber-300"
                                    >
                                        {{ t('Showing the first part of this file.') }}
                                    </p>
                                    <pre
                                        class="max-h-[28rem] overflow-auto rounded-lg border bg-background p-3 text-[11px] leading-relaxed whitespace-pre-wrap"
                                    >{{ preview.content }}</pre>
                                </div>

                                <div
                                    v-else
                                    class="rounded-lg border border-dashed bg-background px-4 py-8 text-center"
                                >
                                    <File class="mx-auto size-8 text-muted-foreground/40" />
                                    <p class="mt-2 text-sm font-medium">
                                        {{ t('Preview not available') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 border-t border-border/60 p-3">
                                <Button as-child variant="outline" size="sm" class="flex-1">
                                    <a :href="downloadUrl(preview.path)">
                                        <Download class="size-4" />
                                        {{ t('Download') }}
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </SettingsPanel>

        <Dialog v-model:open="folderDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('New folder') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('Create a folder in the current storage location.') }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreateFolder">
                    <div class="space-y-2">
                        <Label for="folder-name">{{ t('Folder name') }}</Label>
                        <Input id="folder-name" v-model="folderForm.name" autofocus required />
                        <InputError :message="folderForm.errors.name" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="folderDialogOpen = false">
                            {{ t('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="folderForm.processing">
                            {{ t('Create') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="renameDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('Rename') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('Choose a new name for this item.') }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitRename">
                    <div class="space-y-2">
                        <Label for="rename-name">{{ t('Name') }}</Label>
                        <Input id="rename-name" v-model="renameForm.name" autofocus required />
                        <InputError :message="renameForm.errors.name" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="renameDialogOpen = false">
                            {{ t('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="renameForm.processing">
                            {{ t('Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="moveDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('Move') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('Move this item into another folder in the same storage area.') }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitMove">
                    <div class="space-y-2">
                        <Label for="move-destination">{{ t('Destination folder') }}</Label>
                        <select
                            id="move-destination"
                            v-model="moveForm.destination"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option
                                v-for="option in folderDestinations"
                                :key="option.value || 'root'"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="moveForm.errors.destination" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="moveDialogOpen = false">
                            {{ t('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="moveForm.processing">
                            {{ t('Move') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
    </SettingsPage>
</template>
