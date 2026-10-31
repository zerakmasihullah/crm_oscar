<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/composables/useTranslations';

export type PermissionItem = {
    id: number;
    name: string;
};

const props = defineProps<{
    permissions: PermissionItem[];
    modelValue: string[];
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const { t } = useTranslations();

/** Pinned first — AI access is a first-class access concern. */
const PRIORITY_MODULES = ['ai'];

const AI_META: Record<string, { label: string; description: string }> = {
    'ai.view': {
        label: 'View',
        description: 'See AI Analytics and usage information (read-only).',
    },
    'ai.chat': {
        label: 'Chat',
        description: 'Use the General Chat assistant.',
    },
    'ai.assist': {
        label: 'Assist',
        description: 'Use Ctrl+K writing assist and form fill.',
    },
    'ai.analytics': {
        label: 'Analytics',
        description: 'Generate AI Analytics reports.',
    },
    'ai.manage': {
        label: 'Manage',
        description: 'Change AI settings, models, and prompts.',
    },
};

const AI_ORDER = [
    'ai.view',
    'ai.chat',
    'ai.assist',
    'ai.analytics',
    'ai.manage',
];

const groups = computed(() => {
    const map: Record<string, PermissionItem[]> = {};

    for (const permission of props.permissions) {
        const [module] = permission.name.split('.');
        const key = module ?? permission.name;

        if (!map[key]) {
            map[key] = [];
        }

        map[key].push(permission);
    }

    for (const [module, modulePermissions] of Object.entries(map)) {
        if (module === 'ai') {
            map[module] = [...modulePermissions].sort((a, b) => {
                const ai = AI_ORDER.indexOf(a.name);
                const bi = AI_ORDER.indexOf(b.name);
                const aRank = ai === -1 ? 999 : ai;
                const bRank = bi === -1 ? 999 : bi;

                return aRank - bRank || a.name.localeCompare(b.name);
            });
        }
    }

    return Object.entries(map).sort(([a], [b]) => {
        const aPriority = PRIORITY_MODULES.indexOf(a);
        const bPriority = PRIORITY_MODULES.indexOf(b);

        if (aPriority !== -1 || bPriority !== -1) {
            if (aPriority === -1) {
                return 1;
            }
            if (bPriority === -1) {
                return -1;
            }

            return aPriority - bPriority;
        }

        return a.localeCompare(b);
    });
});

const selected = computed(() => new Set(props.modelValue));

const allSelected = computed(
    () =>
        props.permissions.length > 0 &&
        props.modelValue.length === props.permissions.length,
);

function isChecked(name: string): boolean {
    return selected.value.has(name);
}

function selectAll(): void {
    if (props.disabled) {
        return;
    }

    emit(
        'update:modelValue',
        props.permissions.map((permission) => permission.name),
    );
}

function clearAll(): void {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', []);
}

function moduleState(modulePermissions: PermissionItem[]): boolean | 'indeterminate' {
    const total = modulePermissions.length;
    const count = modulePermissions.filter((p) => selected.value.has(p.name)).length;

    if (count === 0) {
        return false;
    }

    if (count === total) {
        return true;
    }

    return 'indeterminate';
}

function setPermission(name: string, checked: boolean | 'indeterminate'): void {
    if (props.disabled) {
        return;
    }

    const next = new Set(props.modelValue);

    if (checked === true) {
        next.add(name);
    } else {
        next.delete(name);
    }

    emit('update:modelValue', [...next]);
}

function setModule(
    modulePermissions: PermissionItem[],
    checked: boolean | 'indeterminate',
): void {
    if (props.disabled) {
        return;
    }

    const next = new Set(props.modelValue);
    const enable = checked === true;

    for (const permission of modulePermissions) {
        if (enable) {
            next.add(permission.name);
        } else {
            next.delete(permission.name);
        }
    }

    emit('update:modelValue', [...next]);
}

function moduleTitle(module: string): string {
    if (module === 'ai') {
        return t('AI access');
    }

    return module.replaceAll('_', ' ');
}

function actionLabel(name: string): string {
    const meta = AI_META[name];
    if (meta) {
        return t(meta.label);
    }

    const parts = name.split('.');
    const action = parts.length > 1 ? parts.slice(1).join('.') : name;

    return action.replaceAll('_', ' ');
}

function actionDescription(name: string): string | null {
    const meta = AI_META[name];
    if (!meta) {
        return null;
    }

    return t(meta.description);
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-medium">{{ t('Permissions') }}</p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    {{ modelValue.length }} / {{ permissions.length }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="disabled || allSelected"
                    @click="selectAll"
                >
                    {{ t('Select all') }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="disabled || modelValue.length === 0"
                    @click="clearAll"
                >
                    {{ t('Clear all') }}
                </Button>
            </div>
        </div>

        <div class="grid gap-3">
            <div
                v-for="[module, modulePermissions] in groups"
                :key="module"
                class="overflow-hidden rounded-xl border border-border/70 dark:border-white/10"
                :class="
                    module === 'ai'
                        ? 'border-primary/35 ring-1 ring-primary/15'
                        : undefined
                "
            >
                <label
                    class="flex cursor-pointer items-center justify-between gap-3 border-b border-border/60 px-3.5 py-2.5"
                    :class="
                        module === 'ai' ? 'bg-primary/8' : 'bg-muted/30'
                    "
                >
                    <span class="min-w-0">
                        <span
                            class="block text-sm font-semibold tracking-tight capitalize"
                        >
                            {{ moduleTitle(module) }}
                        </span>
                        <span
                            v-if="module === 'ai'"
                            class="mt-0.5 block text-[11px] text-muted-foreground"
                        >
                            {{
                                t(
                                    'Control who can see, use, or change AI — no grants means no access.',
                                )
                            }}
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="text-[11px] text-muted-foreground tabular-nums">
                            {{
                                modulePermissions.filter((p) =>
                                    selected.has(p.name),
                                ).length
                            }}/{{ modulePermissions.length }}
                        </span>
                        <Checkbox
                            :checked="moduleState(modulePermissions)"
                            :disabled="disabled"
                            @update:checked="
                                (value) => setModule(modulePermissions, value)
                            "
                        />
                    </span>
                </label>

                <div
                    class="grid gap-0.5 p-2"
                    :class="
                        module === 'ai' ? 'sm:grid-cols-1' : 'sm:grid-cols-2'
                    "
                >
                    <label
                        v-for="permission in modulePermissions"
                        :key="permission.id"
                        class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2.5 py-2 text-sm transition hover:bg-muted/40"
                    >
                        <Checkbox
                            class="mt-0.5"
                            :checked="isChecked(permission.name)"
                            :disabled="disabled"
                            @update:checked="
                                (value) => setPermission(permission.name, value)
                            "
                        />
                        <span class="min-w-0">
                            <span class="block font-medium capitalize">
                                {{ actionLabel(permission.name) }}
                            </span>
                            <span
                                v-if="actionDescription(permission.name)"
                                class="mt-0.5 block text-[11px] leading-snug text-muted-foreground"
                            >
                                {{ actionDescription(permission.name) }}
                            </span>
                            <span
                                v-else
                                class="block truncate font-mono text-[11px] text-muted-foreground"
                            >
                                {{ permission.name }}
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>
