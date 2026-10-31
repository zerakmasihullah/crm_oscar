<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ClipboardList,
    CornerDownLeft,
    DatabaseBackup,
    FileText,
    LayoutGrid,
    ListTree,
    LoaderCircle,
    Search,
    Settings,
    Shield,
    StickyNote,
    Users,
} from '@lucide/vue';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';

type SearchResultItem = {
    id: string;
    title: string;
    subtitle: string | null;
    href: string;
    type: string;
};

type SearchGroup = {
    key: string;
    label: string;
    items: SearchResultItem[];
};

const { t } = useTranslations();

const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const loading = ref(false);
const groups = ref<SearchGroup[]>([]);
const inputEl = ref<HTMLInputElement | null>(null);
let requestSeq = 0;

const isMac = computed(
    () =>
        typeof navigator !== 'undefined' &&
        /Mac|iPhone|iPad|iPod/i.test(
            navigator.platform || navigator.userAgent,
        ),
);

const shortcutLabel = computed(() => (isMac.value ? '⌘K' : 'Ctrl K'));

const flatResults = computed(() =>
    groups.value.flatMap((group) => group.items),
);

const iconForType: Record<string, typeof Search> = {
    page: LayoutGrid,
    user: Users,
    role: Shield,
    field_report: ClipboardList,
    field_note: StickyNote,
    backup: DatabaseBackup,
    lookup: ListTree,
};

function iconFor(item: SearchResultItem) {
    if (item.type === 'page' && item.href.includes('/settings')) {
        return Settings;
    }

    if (item.type === 'page' && item.href.includes('/field-notes')) {
        return StickyNote;
    }

    return iconForType[item.type] ?? FileText;
}

async function requestJson<T>(url: string): Promise<T> {
    const csrf = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Search failed: ${response.status}`);
    }

    return response.json() as Promise<T>;
}

async function runSearch(term: string): Promise<void> {
    const seq = ++requestSeq;
    loading.value = true;

    try {
        const params = new URLSearchParams();
        if (term !== '') {
            params.set('q', term);
        }

        const data = await requestJson<{ groups: SearchGroup[] }>(
            `/search${params.toString() ? `?${params}` : ''}`,
        );

        if (seq !== requestSeq) {
            return;
        }

        groups.value = data.groups ?? [];
        activeIndex.value = 0;
    } catch {
        if (seq !== requestSeq) {
            return;
        }

        groups.value = [];
    } finally {
        if (seq === requestSeq) {
            loading.value = false;
        }
    }
}

const debouncedSearch = useDebounceFn((term: string) => {
    void runSearch(term);
}, 220);

watch(open, async (isOpen) => {
    if (!isOpen) {
        query.value = '';
        activeIndex.value = 0;
        groups.value = [];
        loading.value = false;
        return;
    }

    await nextTick();
    inputEl.value?.focus();
    await runSearch('');
});

watch(query, (value) => {
    if (!open.value) {
        return;
    }

    loading.value = true;
    debouncedSearch(value.trim());
});

function openSearch(): void {
    open.value = true;
}

function closeSearch(): void {
    open.value = false;
}

function move(delta: number): void {
    const total = flatResults.value.length;
    if (total === 0) {
        activeIndex.value = 0;
        return;
    }

    activeIndex.value = (activeIndex.value + delta + total) % total;
}

function visit(item: SearchResultItem): void {
    closeSearch();
    router.visit(item.href);
}

function onSelectActive(): void {
    const item = flatResults.value[activeIndex.value];
    if (item) {
        visit(item);
    }
}

function onInputKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        onSelectActive();
    }
}

useEventListener('keydown', (event: KeyboardEvent) => {
    if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'k') {
        return;
    }

    event.preventDefault();
    open.value = !open.value;
});

defineExpose({ openSearch });
</script>

<template>
    <div data-global-search-root>
        <button
            type="button"
            class="inline-flex size-9 items-center justify-center rounded-full text-zinc-700 transition hover:bg-white/70 hover:text-zinc-900 dark:text-zinc-200 dark:hover:bg-white/10 dark:hover:text-white"
            :aria-label="t('Search')"
            :title="`${t('Search')} (${shortcutLabel})`"
            @click="openSearch"
        >
            <Search class="size-4 shrink-0" stroke-width="1.75" />
        </button>

        <Dialog :open="open" @update:open="open = $event">
            <DialogContent
                :show-close-button="false"
                class="top-[12%] translate-y-0 gap-0 overflow-hidden rounded-[1.75rem] border-white/60 bg-white/92 p-0 shadow-[0_30px_80px_-28px_rgba(15,23,42,0.45)] backdrop-blur-2xl sm:max-w-xl dark:border-white/10 dark:bg-card/95"
            >
                <DialogTitle class="sr-only">{{ t('Search') }}</DialogTitle>
                <DialogDescription class="sr-only">
                    {{ t('Search pages, users, and records') }}
                </DialogDescription>

                <div
                    class="flex items-center gap-3 border-b border-zinc-900/5 px-4 py-3 dark:border-white/10"
                >
                    <LoaderCircle
                        v-if="loading"
                        class="size-4 shrink-0 animate-spin text-zinc-400"
                        stroke-width="1.75"
                    />
                    <Search
                        v-else
                        class="size-4 shrink-0 text-zinc-400"
                        stroke-width="1.75"
                    />
                    <input
                        ref="inputEl"
                        v-model="query"
                        type="search"
                        autocomplete="off"
                        spellcheck="false"
                        class="h-10 w-full bg-transparent text-[15px] text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-100"
                        :placeholder="t('Search pages, users, and records…')"
                        @keydown="onInputKeydown"
                    />
                    <kbd
                        class="hidden rounded-full bg-zinc-900/5 px-2 py-1 text-[10px] font-semibold text-zinc-500 sm:inline-flex dark:bg-white/10"
                    >
                        esc
                    </kbd>
                </div>

                <div class="max-h-[min(26rem,58vh)] overflow-y-auto p-2">
                    <div
                        v-if="!loading && flatResults.length === 0"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        <template v-if="query.trim().length > 0 && query.trim().length < 2">
                            {{ t('Type at least 2 characters to search records') }}
                        </template>
                        <template v-else>
                            {{ t('No results found') }}
                        </template>
                    </div>

                    <div
                        v-for="group in groups"
                        :key="group.key"
                        class="mb-1"
                    >
                        <p
                            class="px-3 py-2 text-[10px] font-semibold tracking-[0.14em] text-zinc-400 uppercase"
                        >
                            {{ group.label }}
                        </p>
                        <button
                            v-for="item in group.items"
                            :key="item.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-start transition"
                            :class="
                                flatResults[activeIndex]?.id === item.id
                                    ? 'bg-zinc-900 text-white shadow-[0_12px_28px_-16px_rgba(15,23,42,0.65)]'
                                    : 'text-zinc-700 hover:bg-zinc-900/5 dark:text-zinc-200 dark:hover:bg-white/5'
                            "
                            @mouseenter="
                                activeIndex = flatResults.findIndex(
                                    (entry) => entry.id === item.id,
                                )
                            "
                            @click="visit(item)"
                        >
                            <span
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    flatResults[activeIndex]?.id === item.id
                                        ? 'bg-white/15 text-white'
                                        : 'bg-zinc-900/5 text-zinc-600 dark:bg-white/10 dark:text-zinc-200'
                                "
                            >
                                <component
                                    :is="iconFor(item)"
                                    class="size-4"
                                    stroke-width="1.75"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium"
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    v-if="item.subtitle"
                                    class="mt-0.5 block truncate text-[11px]"
                                    :class="
                                        flatResults[activeIndex]?.id === item.id
                                            ? 'text-white/65'
                                            : 'text-zinc-400'
                                    "
                                >
                                    {{ item.subtitle }}
                                </span>
                            </span>
                            <CornerDownLeft
                                v-if="flatResults[activeIndex]?.id === item.id"
                                class="size-3.5 shrink-0 opacity-70"
                                stroke-width="1.75"
                            />
                        </button>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between gap-3 border-t border-zinc-900/5 px-4 py-2.5 text-[11px] text-zinc-400 dark:border-white/10"
                >
                    <span>{{ t('Navigate with ↑ ↓ · Enter to open') }}</span>
                    <span class="font-medium">{{ shortcutLabel }}</span>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
