<script setup lang="ts">
import {
    Check,
    CornerDownLeft,
    LoaderCircle,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useAiInlineEdit } from '@/composables/useAiInlineEdit';
import { useTranslations } from '@/composables/useTranslations';

const PANEL_SIZE_KEY = 'kit.ai.panel_size';

const { t } = useTranslations();
const {
    open,
    target,
    prompt,
    draft,
    modelId,
    action,
    phase,
    lastUsage,
    processing,
    error,
    settings,
    available,
    isEligibleField,
    openFor,
    openFromActiveElement,
    close,
    accept,
    reject,
    runPrompt,
} = useAiInlineEdit();

const promptRef = ref<HTMLTextAreaElement | null>(null);
const sparkle = ref<{
    top: number;
    left: number;
    el: HTMLInputElement | HTMLTextAreaElement;
} | null>(null);
/** Avoid Teleport-to-body SSR hydration mismatches against #app / Inertia page JSON. */
const mounted = ref(false);

const panelSizeId = ref('comfortable');

const shortcutLabel = computed(() =>
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/i.test(navigator.platform)
        ? '⌘K'
        : 'Ctrl+K',
);

const sizeOptions = computed(() => settings.value.panel_sizes ?? []);

const activeSize = computed(() => {
    const sizes = sizeOptions.value;
    return (
        sizes.find((s) => s.id === panelSizeId.value) ??
        sizes.find((s) => s.id === settings.value.panel_size) ??
        sizes[0] ?? {
            id: 'comfortable',
            label: 'Comfortable',
            width: 420,
            prompt_rows: 4,
            result_max_h: 180,
        }
    );
});

const needsPrompt = computed(
    () => action.value === 'custom' || phase.value === 'result',
);

const canSubmit = computed(() => {
    if (processing.value) {
        return false;
    }
    if (needsPrompt.value) {
        return Boolean(prompt.value.trim());
    }
    return true;
});

const promptPlaceholder = computed(() => {
    if (phase.value === 'result') {
        return t('Follow up…');
    }
    if (action.value === 'custom') {
        return t('Ask AI to edit or write…');
    }
    return t('Optional note… (Enter to run)');
});

const panelStyle = computed(() => {
    const rect = target.value?.rect;
    if (!rect) {
        return {};
    }

    const width = Math.min(
        activeSize.value.width,
        Math.max(300, window.innerWidth - 24),
    );
    let left = rect.left;
    left = Math.min(left, window.innerWidth - width - 12);
    left = Math.max(left, 12);

    const spaceBelow = window.innerHeight - rect.bottom;
    const preferBelow = spaceBelow > 280;

    if (preferBelow) {
        return {
            top: `${rect.bottom + 8}px`,
            left: `${left}px`,
            width: `${width}px`,
        };
    }

    return {
        top: `${Math.max(12, rect.top - 8)}px`,
        left: `${left}px`,
        width: `${width}px`,
        transform: 'translateY(-100%)',
    };
});

const contextPreview = computed(() => {
    const text = target.value?.selected || '';
    if (!text) {
        return t('Empty field');
    }
    return text.length > 160 ? `${text.slice(0, 160)}…` : text;
});

function resolvePanelSize(): void {
    const allowed = new Set(sizeOptions.value.map((s) => s.id));
    const local =
        typeof localStorage !== 'undefined'
            ? localStorage.getItem(PANEL_SIZE_KEY)
            : null;
    const org = settings.value.panel_size;

    if (local && allowed.has(local)) {
        panelSizeId.value = local;
    } else if (org && allowed.has(org)) {
        panelSizeId.value = org;
    } else if (allowed.size) {
        panelSizeId.value = [...allowed][0]!;
    }
}

function setPanelSize(id: string): void {
    panelSizeId.value = id;
    try {
        localStorage.setItem(PANEL_SIZE_KEY, id);
    } catch {
        // ignore quota / private mode
    }
}

watch(open, async (isOpen) => {
    if (isOpen) {
        sparkle.value = null;
        resolvePanelSize();
        await nextTick();
        promptRef.value?.focus();
    }
});

watch(
    () => settings.value.panel_size,
    () => {
        if (!open.value) {
            resolvePanelSize();
        }
    },
);

function updateSparkle(): void {
    if (!available.value || open.value) {
        sparkle.value = null;
        return;
    }

    const el = document.activeElement;
    if (!isEligibleField(el)) {
        sparkle.value = null;
        return;
    }

    const rect = el.getBoundingClientRect();
    sparkle.value = {
        top: Math.max(8, rect.top + rect.height / 2 - 16),
        left: Math.min(window.innerWidth - 40, rect.right + 8),
        el,
    };
}

function onKeydown(event: KeyboardEvent): void {
    if (!available.value) {
        return;
    }

    const mod = event.metaKey || event.ctrlKey;
    if (mod && event.key.toLowerCase() === 'k') {
        if (!isEligibleField(document.activeElement)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        openFromActiveElement();
        return;
    }

    if (!open.value) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
}

function onFocusIn(): void {
    updateSparkle();
}

function onFocusOut(): void {
    window.setTimeout(() => {
        if (!open.value) {
            updateSparkle();
        }
    }, 10);
}

function onScrollOrResize(): void {
    if (open.value && target.value) {
        target.value = {
            ...target.value,
            rect: target.value.el.getBoundingClientRect(),
        };
    }
    updateSparkle();
}

function magicClick(): void {
    const el = sparkle.value?.el;
    if (el) {
        openFor(el);
    }
}

async function submitPrompt(): Promise<void> {
    await runPrompt(phase.value === 'result');
}

onMounted(() => {
    mounted.value = true;
    resolvePanelSize();
    window.addEventListener('keydown', onKeydown, true);
    document.addEventListener('focusin', onFocusIn);
    document.addEventListener('focusout', onFocusOut);
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown, true);
    document.removeEventListener('focusin', onFocusIn);
    document.removeEventListener('focusout', onFocusOut);
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
});
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <button
            v-if="sparkle"
            type="button"
            class="fixed z-[80] flex size-8 items-center justify-center rounded-full border border-foreground/20 bg-card text-foreground shadow-md transition hover:bg-foreground hover:text-background"
            :style="{ top: `${sparkle.top}px`, left: `${sparkle.left}px` }"
            :title="t('AI edit (:shortcut)', { shortcut: shortcutLabel })"
            @mousedown.prevent
            @click="magicClick"
        >
            <Sparkles class="size-3.5" :stroke-width="1.75" />
        </button>

        <div
            v-if="open && target"
            class="fixed inset-0 z-[90]"
            @mousedown.self="close"
        >
            <div
                class="absolute overflow-hidden rounded-xl border bg-background shadow-xl"
                :style="panelStyle"
                role="dialog"
                :aria-label="t('AI edit')"
                @mousedown.stop
            >
                <div
                    class="flex flex-wrap items-center gap-2 border-b px-3 py-2"
                >
                    <Sparkles
                        class="size-3.5 shrink-0 text-foreground"
                        :stroke-width="1.75"
                    />

                    <select
                        v-model="action"
                        class="h-7 max-w-[10rem] rounded-md border bg-transparent px-2 text-xs font-medium"
                        :disabled="processing || phase === 'result'"
                        :aria-label="t('Action')"
                    >
                        <option
                            v-for="item in settings.actions"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ t(item.label) }}
                        </option>
                    </select>

                    <select
                        v-if="settings.models.length > 1"
                        v-model="modelId"
                        class="h-7 max-w-[8rem] rounded-md border bg-transparent px-2 text-xs"
                        :disabled="processing"
                        :aria-label="t('Model')"
                    >
                        <option
                            v-for="model in settings.models"
                            :key="model.id"
                            :value="model.id"
                        >
                            {{ model.label }}
                        </option>
                    </select>

                    <div
                        v-if="sizeOptions.length > 1"
                        class="ms-auto flex items-center gap-0.5 rounded-md border p-0.5"
                        :title="t('Panel size')"
                    >
                        <button
                            v-for="size in sizeOptions"
                            :key="size.id"
                            type="button"
                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold transition"
                            :class="
                                panelSizeId === size.id
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="setPanelSize(size.id)"
                        >
                            {{ size.id === 'compact' ? 'S' : size.id === 'large' ? 'L' : 'M' }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        :class="{ 'ms-auto': sizeOptions.length <= 1 }"
                        :aria-label="t('Close')"
                        @click="close"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>

                <div class="space-y-2 px-3 py-2">
                    <p class="text-[11px] font-medium text-muted-foreground">
                        {{
                            target.replaceSelection
                                ? t('Selected text')
                                : t('Field text')
                        }}
                    </p>
                    <div
                        class="max-h-16 overflow-auto rounded-md bg-muted/50 px-2 py-1.5 text-xs leading-relaxed text-foreground/80"
                    >
                        {{ contextPreview }}
                    </div>

                    <div
                        v-if="phase === 'result' && draft"
                        class="overflow-auto rounded-md border bg-muted/30 px-2.5 py-2 text-sm leading-relaxed whitespace-pre-wrap"
                        :style="{ maxHeight: `${activeSize.result_max_h}px` }"
                    >
                        {{ draft }}
                    </div>

                    <p
                        v-if="lastUsage"
                        class="text-[10px] text-muted-foreground"
                    >
                        {{ lastUsage.total_tokens }} {{ t('tokens') }}
                        <span v-if="lastUsage.duration_ms">
                            · {{ lastUsage.duration_ms }}ms
                        </span>
                        <span v-if="lastUsage.estimated_cost_usd != null">
                            · ${{ lastUsage.estimated_cost_usd.toFixed(5) }}
                        </span>
                    </p>

                    <div class="relative">
                        <textarea
                            ref="promptRef"
                            v-model="prompt"
                            :rows="activeSize.prompt_rows"
                            :maxlength="settings.max_instruction_chars"
                            class="min-h-[4.5rem] w-full resize-y rounded-md border bg-transparent px-3 py-2 pe-10 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            :placeholder="promptPlaceholder"
                            :disabled="processing"
                            @keydown.enter.exact.prevent="
                                canSubmit && submitPrompt()
                            "
                        />
                        <button
                            type="button"
                            class="absolute end-2 bottom-2 rounded-md p-1 text-foreground hover:bg-muted disabled:opacity-40"
                            :disabled="!canSubmit"
                            :aria-label="t('Send')"
                            @click="submitPrompt"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            />
                            <CornerDownLeft v-else class="size-4" />
                        </button>
                    </div>

                    <p
                        v-if="error"
                        class="text-xs text-destructive"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                </div>

                <div
                    v-if="phase === 'result'"
                    class="flex items-center justify-end gap-2 border-t px-3 py-2"
                >
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        :disabled="processing"
                        @click="reject"
                    >
                        {{ t('Reject') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :disabled="processing || !draft"
                        @click="accept"
                    >
                        <Check class="size-3.5" />
                        {{ t('Accept') }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
