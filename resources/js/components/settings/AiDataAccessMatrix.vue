<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { MisSection } from '@/components/mis';
import SettingsSwitch from '@/components/settings/SettingsSwitch.vue';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

export type AiResourceOption = {
    key: string;
    label: string;
    table: string | null;
    model: string | null;
};

export type AiResourceActions = {
    view: boolean;
    create: boolean;
    edit: boolean;
    delete: boolean;
};

type AccessFilter = 'all' | 'full' | 'read' | 'custom' | 'off';
type AccessAction = keyof AiResourceActions;
type AccessPreset = 'full' | 'read' | 'off';

const props = defineProps<{
    resources: AiResourceOption[];
    modelValue: Record<string, AiResourceActions>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Record<string, AiResourceActions>];
}>();

const { t } = useTranslations();

const query = ref('');
const filter = ref<AccessFilter>('all');

const ACTIONS: AccessAction[] = ['view', 'create', 'edit', 'delete'];

function asAccessBool(value: unknown, fallback: boolean): boolean {
    if (value === true || value === 1 || value === '1' || value === 'true') {
        return true;
    }

    if (value === false || value === 0 || value === '0' || value === 'false') {
        return false;
    }

    return fallback;
}

function actionsFor(key: string): AiResourceActions {
    const row = props.modelValue[key];
    const view = asAccessBool(row?.view, true);

    return {
        view,
        create: view && asAccessBool(row?.create, true),
        edit: view && asAccessBool(row?.edit, true),
        delete: view && asAccessBool(row?.delete, true),
    };
}

function modeFor(actions: AiResourceActions): AccessFilter {
    if (!actions.view) {
        return 'off';
    }

    if (actions.create && actions.edit && actions.delete) {
        return 'full';
    }

    if (!actions.create && !actions.edit && !actions.delete) {
        return 'read';
    }

    return 'custom';
}

function preset(kind: AccessPreset): AiResourceActions {
    if (kind === 'off') {
        return { view: false, create: false, edit: false, delete: false };
    }

    if (kind === 'read') {
        return { view: true, create: false, edit: false, delete: false };
    }

    return { view: true, create: true, edit: true, delete: true };
}

function cloneAccess(
    source: Record<string, AiResourceActions>,
): Record<string, AiResourceActions> {
    const next: Record<string, AiResourceActions> = {};

    for (const resource of props.resources) {
        const value = source[resource.key];
        const view = asAccessBool(value?.view, true);
        next[resource.key] = {
            view,
            create: view && asAccessBool(value?.create, true),
            edit: view && asAccessBool(value?.edit, true),
            delete: view && asAccessBool(value?.delete, true),
        };
    }

    for (const [key, value] of Object.entries(source)) {
        if (key in next) {
            continue;
        }

        const view = asAccessBool(value?.view, true);
        next[key] = {
            view,
            create: view && asAccessBool(value?.create, true),
            edit: view && asAccessBool(value?.edit, true),
            delete: view && asAccessBool(value?.delete, true),
        };
    }

    return next;
}

function normalize(next: AiResourceActions): AiResourceActions {
    if (! asAccessBool(next.view, true)) {
        return { view: false, create: false, edit: false, delete: false };
    }

    return {
        view: true,
        create: asAccessBool(next.create, true),
        edit: asAccessBool(next.edit, true),
        delete: asAccessBool(next.delete, true),
    };
}

function patch(key: string, next: AiResourceActions): void {
    const updated = cloneAccess(props.modelValue);
    updated[key] = normalize(next);
    emit('update:modelValue', updated);
}

function setEnabled(key: string, enabled: boolean): void {
    patch(key, enabled ? preset('full') : preset('off'));
}

function setAction(key: string, action: AccessAction, value: boolean): void {
    const current = actionsFor(key);

    if (action === 'view') {
        if (!value) {
            patch(key, preset('off'));
            return;
        }

        // Turning View on restores full access by default.
        patch(key, preset('full'));
        return;
    }

    if (!current.view) {
        return;
    }

    patch(key, { ...current, [action]: value });
}

function applyToFiltered(kind: AccessPreset): void {
    if (props.disabled || filteredResources.value.length === 0) {
        return;
    }

    const next = cloneAccess(props.modelValue);

    for (const resource of filteredResources.value) {
        next[resource.key] = preset(kind);
    }

    emit('update:modelValue', next);
}

function columnState(action: AccessAction): boolean | 'indeterminate' {
    const list = filteredResources.value;
    if (list.length === 0) {
        return false;
    }

    const enabled = list.filter((row) => {
        if (action === 'view') {
            return row.actions.view;
        }

        return row.actions.view && row.actions[action];
    }).length;

    if (enabled === 0) {
        return false;
    }

    if (enabled === list.length) {
        return true;
    }

    return 'indeterminate';
}

function toggleColumn(action: AccessAction, checked: boolean | 'indeterminate'): void {
    if (props.disabled || filteredResources.value.length === 0) {
        return;
    }

    const enable = checked === true;
    const next = cloneAccess(props.modelValue);

    for (const row of filteredResources.value) {
        if (action === 'view') {
            next[row.key] = enable ? preset('full') : preset('off');
            continue;
        }

        const current = actionsFor(row.key);
        if (!current.view) {
            continue;
        }

        next[row.key] = normalize({ ...current, [action]: enable });
    }

    emit('update:modelValue', next);
}

const rows = computed(() =>
    props.resources.map((resource) => {
        const actions = actionsFor(resource.key);

        return {
            ...resource,
            actions,
            mode: modeFor(actions),
        };
    }),
);

const filteredResources = computed(() => {
    const q = query.value.trim().toLowerCase();

    return rows.value.filter((row) => {
        if (filter.value !== 'all' && row.mode !== filter.value) {
            return false;
        }

        if (!q) {
            return true;
        }

        return (
            row.label.toLowerCase().includes(q) ||
            row.key.toLowerCase().includes(q) ||
            (row.table?.toLowerCase().includes(q) ?? false) ||
            (row.model?.toLowerCase().includes(q) ?? false)
        );
    });
});

const summary = computed(() => {
    let full = 0;
    let read = 0;
    let custom = 0;
    let off = 0;

    for (const row of rows.value) {
        if (row.mode === 'full') {
            full += 1;
        } else if (row.mode === 'read') {
            read += 1;
        } else if (row.mode === 'custom') {
            custom += 1;
        } else {
            off += 1;
        }
    }

    return { full, read, custom, off, total: rows.value.length };
});

const filters = computed(() => [
    { id: 'all' as const, label: t('All'), count: summary.value.total },
    { id: 'full' as const, label: t('Full'), count: summary.value.full },
    { id: 'read' as const, label: t('Read'), count: summary.value.read },
    { id: 'custom' as const, label: t('Custom'), count: summary.value.custom },
    { id: 'off' as const, label: t('Off'), count: summary.value.off },
]);

function actionLabel(action: AccessAction): string {
    if (action === 'view') {
        return t('View');
    }
    if (action === 'create') {
        return t('Create');
    }
    if (action === 'edit') {
        return t('Edit');
    }

    return t('Delete');
}

function clearSearch(): void {
    query.value = '';
}
</script>

<template>
    <MisSection
        :title="t('Data access')"
        :description="
            t('Control what AI can view or change. Changes apply to everyone.')
        "
    >
        <div class="space-y-4">
            <div class="flex flex-col gap-3">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="query"
                        type="search"
                        class="h-10 pr-9 pl-9"
                        :placeholder="t('Search resources…')"
                        :disabled="disabled"
                    />
                    <button
                        v-if="query"
                        type="button"
                        class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        :aria-label="t('Clear')"
                        @click="clearSearch"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex flex-wrap gap-1 rounded-xl bg-muted/50 p-1"
                        role="tablist"
                    >
                        <button
                            v-for="item in filters"
                            :key="item.id"
                            type="button"
                            role="tab"
                            :aria-selected="filter === item.id"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
                            :class="
                                filter === item.id
                                    ? 'bg-card text-foreground shadow-soft'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :disabled="disabled"
                            @click="filter = item.id"
                        >
                            <span>{{ item.label }}</span>
                            <span
                                class="tabular-nums opacity-60"
                                :class="
                                    filter === item.id
                                        ? 'opacity-70'
                                        : undefined
                                "
                            >
                                {{ item.count }}
                            </span>
                        </button>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                    >
                        <button
                            type="button"
                            class="font-medium text-foreground/80 transition hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            :disabled="disabled || filteredResources.length === 0"
                            @click="applyToFiltered('full')"
                        >
                            {{ t('Allow all') }}
                        </button>
                        <span class="text-border">·</span>
                        <button
                            type="button"
                            class="font-medium text-foreground/80 transition hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            :disabled="disabled || filteredResources.length === 0"
                            @click="applyToFiltered('read')"
                        >
                            {{ t('Read only') }}
                        </button>
                        <span class="text-border">·</span>
                        <button
                            type="button"
                            class="font-medium text-muted-foreground transition hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            :disabled="disabled || filteredResources.length === 0"
                            @click="applyToFiltered('off')"
                        >
                            {{ t('Turn off') }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="resources.length === 0"
                class="rounded-xl border border-dashed border-border/70 px-4 py-12 text-center text-sm text-muted-foreground"
            >
                {{ t('No AI resources are registered yet.') }}
            </div>

            <div
                v-else-if="filteredResources.length === 0"
                class="rounded-xl border border-dashed border-border/70 px-4 py-12 text-center text-sm text-muted-foreground"
            >
                {{ t('No resources match your search.') }}
            </div>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-border/60"
            >
                <div class="max-h-[min(68vh,36rem)] overflow-auto">
                    <table class="w-full min-w-[26rem] border-collapse text-sm">
                        <thead
                            class="sticky top-0 z-10 border-b border-border/60 bg-card/95 backdrop-blur supports-[backdrop-filter]:bg-card/80"
                        >
                            <tr>
                                <th
                                    class="w-16 px-3 py-3 text-center text-xs font-medium text-muted-foreground"
                                >
                                    {{ t('On') }}
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-medium text-muted-foreground"
                                >
                                    {{ t('Resource') }}
                                    <span
                                        class="ml-1.5 font-normal tabular-nums opacity-70"
                                    >
                                        {{ filteredResources.length }}
                                    </span>
                                </th>
                                <th
                                    v-for="action in ACTIONS"
                                    :key="action"
                                    class="w-[4.5rem] px-2 py-3 text-center"
                                >
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span
                                            class="text-[11px] font-medium text-muted-foreground"
                                        >
                                            {{ actionLabel(action) }}
                                        </span>
                                        <Checkbox
                                            :model-value="columnState(action)"
                                            :disabled="disabled"
                                            @update:model-value="
                                                (value) =>
                                                    toggleColumn(action, value)
                                            "
                                        />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in filteredResources"
                                :key="row.key"
                                class="border-b border-border/40 transition last:border-0 hover:bg-muted/30"
                                :class="
                                    cn(
                                        !row.actions.view &&
                                            'bg-muted/15 text-muted-foreground',
                                    )
                                "
                            >
                                <td class="px-3 py-2.5 text-center align-middle">
                                    <div class="flex justify-center">
                                        <SettingsSwitch
                                            :model-value="row.actions.view"
                                            :disabled="disabled"
                                            @update:model-value="
                                                (value) =>
                                                    setEnabled(row.key, value)
                                            "
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 align-middle">
                                    <span
                                        class="font-medium tracking-tight"
                                        :class="
                                            row.actions.view
                                                ? 'text-foreground'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ row.label }}
                                    </span>
                                </td>
                                <td
                                    v-for="action in ACTIONS"
                                    :key="action"
                                    class="px-2 py-2.5 text-center align-middle"
                                >
                                    <div class="flex justify-center">
                                        <Checkbox
                                            :model-value="row.actions[action]"
                                            :disabled="
                                                disabled || !row.actions.view
                                            "
                                            @update:model-value="
                                                (value) =>
                                                    setAction(
                                                        row.key,
                                                        action,
                                                        value === true,
                                                    )
                                            "
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </MisSection>
</template>
