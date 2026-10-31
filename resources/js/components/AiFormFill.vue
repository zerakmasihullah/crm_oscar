<script setup lang="ts">
import {
    Check,
    LoaderCircle,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useAiFormFill } from '@/composables/useAiFormFill';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const {
    open,
    fields,
    prompt,
    modelId,
    phase,
    preview,
    lastUsage,
    processing,
    error,
    settings,
    available,
    openFromActiveElement,
    openFor,
    close,
    accept,
    reject,
    run,
    findFormRoot,
} = useAiFormFill();

const promptRef = ref<HTMLTextAreaElement | null>(null);
/** Avoid Teleport-to-body SSR hydration mismatches against #app / Inertia page JSON. */
const mounted = ref(false);

const canSubmit = computed(
    () => !processing.value && Boolean(prompt.value.trim()),
);

function onKeydown(event: KeyboardEvent): void {
    if (!available.value) {
        return;
    }

    const isMod = event.metaKey || event.ctrlKey;
    if (!isMod || !event.shiftKey || event.key.toLowerCase() !== 'k') {
        return;
    }

    // Let plain Ctrl+K stay for single-field edit.
    event.preventDefault();
    event.stopPropagation();

    if (open.value) {
        close();
        return;
    }

    openFromActiveElement();
}

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }
    await nextTick();
    promptRef.value?.focus();
});

function onFillFormEvent(event: Event): void {
    if (!available.value) {
        return;
    }
    const detail = (event as CustomEvent<{ el?: Element | null }>).detail;
    const from = detail?.el ?? document.activeElement;
    const root =
        from instanceof Element
            ? from.closest<HTMLElement>('form') ||
              from.closest<HTMLElement>('[data-ai-form]')
            : null;
    openFor(root ?? findFormRoot(from instanceof Element ? from : null));
}

onMounted(() => {
    mounted.value = true;
    window.addEventListener('keydown', onKeydown, true);
    window.addEventListener('kit-ai-fill-form', onFillFormEvent);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown, true);
    window.removeEventListener('kit-ai-fill-form', onFillFormEvent);
});
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-end justify-center bg-black/25 p-4 sm:items-center"
            @click.self="close"
        >
            <div
                role="dialog"
                aria-modal="true"
                class="flex max-h-[min(90vh,640px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border bg-background shadow-2xl"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b px-4 py-3"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <Sparkles class="size-4 shrink-0 text-foreground" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ t('Fill form with AI') }}
                            </p>
                            <p class="truncate text-[11px] text-muted-foreground">
                                {{
                                    t(':count fields detected', {
                                        count: String(fields.length),
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            v-if="settings.models.length > 1"
                            v-model="modelId"
                            class="border-input bg-transparent h-8 max-w-[9rem] rounded-md border px-2 text-xs outline-none"
                        >
                            <option
                                v-for="model in settings.models"
                                :key="model.id"
                                :value="model.id"
                            >
                                {{ model.label }}
                            </option>
                        </select>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :aria-label="t('Close')"
                            @click="close"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-3">
                    <p class="text-xs text-muted-foreground">
                        {{
                            t(
                                'Describe the person or record in plain language. AI maps names, places, gender, and similar details onto the labelled fields — it does not send existing field values.',
                            )
                        }}
                    </p>

                    <textarea
                        ref="promptRef"
                        v-model="prompt"
                        rows="4"
                        class="border-input bg-transparent min-h-[5.5rem] w-full resize-y rounded-md border px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :placeholder="
                            t(
                                'e.g. Fatima Ahmadi, female, from Herat, phone 0700…, notes about enrolment…',
                            )
                        "
                        @keydown.enter.meta.prevent="canSubmit && run()"
                        @keydown.enter.ctrl.prevent="canSubmit && run()"
                    />

                    <div
                        v-if="phase === 'result' && preview.length"
                        class="space-y-2 rounded-xl border p-3"
                    >
                        <p class="text-xs font-semibold tracking-tight">
                            {{ t('Preview') }}
                        </p>
                        <dl class="space-y-2">
                            <div
                                v-for="row in preview"
                                :key="row.id"
                                class="grid gap-0.5 sm:grid-cols-[8rem_1fr]"
                            >
                                <dt
                                    class="text-[11px] font-medium text-muted-foreground"
                                >
                                    {{ row.label }}
                                </dt>
                                <dd class="text-sm break-words">
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="lastUsage"
                            class="text-[11px] text-muted-foreground"
                        >
                            {{ lastUsage.total_tokens }}
                            {{ t('tokens') }}
                            <template v-if="lastUsage.duration_ms != null">
                                · {{ lastUsage.duration_ms }} ms
                            </template>
                        </p>
                    </div>

                    <p v-if="error" class="text-sm text-destructive">
                        {{ error }}
                    </p>
                </div>

                <div
                    class="flex flex-wrap items-center justify-end gap-2 border-t px-4 py-3"
                >
                    <template v-if="phase === 'result'">
                        <Button type="button" variant="outline" @click="reject">
                            {{ t('Reject') }}
                        </Button>
                        <Button type="button" @click="accept">
                            <Check class="size-4" />
                            {{ t('Accept all') }}
                        </Button>
                    </template>
                    <template v-else>
                        <Button type="button" variant="outline" @click="close">
                            {{ t('Cancel') }}
                        </Button>
                        <Button
                            type="button"
                            :disabled="!canSubmit"
                            @click="run"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            />
                            <Sparkles v-else class="size-4" />
                            {{ t('Fill') }}
                        </Button>
                    </template>
                </div>
            </div>
        </div>
    </Teleport>
</template>
